<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vente;
use App\Models\Depense;
use App\Models\Perte;
use App\Models\Achat;
use App\Models\Boutique;
use App\Models\Stock;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RapportController extends Controller
{
    public function index(Request $request)
    {
        $mois = $request->input('mois', date('m'));
        $annee = $request->input('annee', date('Y'));

        $startDate = Carbon::createFromDate($annee, $mois, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($annee, $mois, 1)->endOfMonth();

        // 1. Total Ventes (Sur la période)
        $totalVentes = Vente::whereBetween('created_at', [$startDate, $endDate])->sum('montant_total');

        // 2. Dépenses validées
        $totalDepenses = Depense::where('statut', 'approved')->whereBetween('created_at', [$startDate, $endDate])->sum('montant');
        $totalDepensesPending = Depense::where('statut', 'pending')->whereBetween('created_at', [$startDate, $endDate])->sum('montant');

        // 3. Pertes validées
        $totalPertes = Perte::where('statut', 'approved')->whereBetween('created_at', [$startDate, $endDate])->count();
        $totalPertesPending = Perte::where('statut', 'pending')->whereBetween('created_at', [$startDate, $endDate])->count();

        // 4. Total Achats (Stock entrant)
        $totalAchats = Achat::whereBetween('created_at', [$startDate, $endDate])->sum('montant_total');

        // Flux de trésorerie (Bénéfice estimé global)
        $cashFlow = $totalVentes - ($totalDepenses + $totalAchats);

        // Ventes par boutique
        $ventesParBoutique = Boutique::withSum(['ventes' => function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }], 'montant_total')->get();

        // Statistiques mensuelles pour le graphique (6 derniers mois)
        $statsMensuelles = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $v = Vente::whereBetween('created_at', [$monthStart, $monthEnd])->sum('montant_total');
            $d = Depense::where('statut', 'approved')->whereBetween('created_at', [$monthStart, $monthEnd])->sum('montant');

            $statsMensuelles[] = [
                'mois' => ucfirst($date->translatedFormat('F Y')),
                'ventes' => $v,
                'depenses' => $d
            ];
        }

        $pendingDepenses = Depense::with(['boutique', 'user'])
            ->where('statut', 'pending')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $pendingPertes = Perte::with(['boutique', 'produit', 'user'])
            ->where('statut', 'pending')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        return view('admin.rapports.index', compact(
            'totalVentes',
            'totalDepenses',
            'totalDepensesPending',
            'totalPertes',
            'totalPertesPending',
            'totalAchats',
            'cashFlow',
            'ventesParBoutique',
            'statsMensuelles',
            'pendingDepenses',
            'pendingPertes',
            'mois',
            'annee'
        ));
    }

    public function exportCsv(Request $request)
    {
        $mois = $request->input('mois', date('m'));
        $annee = $request->input('annee', date('Y'));
        $startDate = Carbon::createFromDate($annee, $mois, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($annee, $mois, 1)->endOfMonth();

        $ventes = Vente::with('boutique')->whereBetween('created_at', [$startDate, $endDate])->get();

        $filename = "rapport_ventes_{$annee}_{$mois}.csv";

        // Ajout du BOM pour qu'Excel ouvre le CSV correctement en UTF-8
        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function () use ($ventes) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // BOM UTF-8

            fputcsv($file, ['Date', 'Boutique', 'Montant (FCFA)', 'Statut'], ';');

            foreach ($ventes as $vente) {
                fputcsv($file, [
                    $vente->created_at->format('d/m/Y H:i'),
                    $vente->boutique->nom ?? 'Inconnue',
                    $vente->montant_total,
                    $vente->statut ?? 'Payée'
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function approveDepense(Depense $depense)
    {
        if ($depense->statut !== 'pending') {
            return back()->with('error', 'Cette dépense ne peut pas être validée.');
        }

        DB::transaction(function () use ($depense) {
            $depense->update([
                'statut' => 'approved',
                'admin_id' => Auth::id(),
                'validated_at' => now(),
            ]);

            if ($depense->boutique_id) {
                $boutique = Boutique::find($depense->boutique_id);
                if ($boutique) {
                    $boutique->decrement('solde', $depense->montant);
                }
            }
        });

        return back()->with('success', 'Dépense validée avec succès.');
    }

    public function rejectDepense(Request $request, Depense $depense)
    {
        $request->validate([
            'rejet_reason' => 'required|string|min:5',
        ]);

        if ($depense->statut !== 'pending') {
            return back()->with('error', 'Cette dépense ne peut pas être rejetée.');
        }

        $depense->update([
            'statut' => 'rejected',
            'admin_id' => Auth::id(),
            'rejet_reason' => $request->rejet_reason,
            'validated_at' => now(),
        ]);

        return back()->with('success', 'Dépense rejetée avec succès.');
    }

    public function approvePerte(Perte $perte)
    {
        if ($perte->statut !== 'pending') {
            return back()->with('error', 'Cette perte ne peut pas être validée.');
        }

        $stock = Stock::where('boutique_id', $perte->boutique_id)
            ->where('produit_id', $perte->produit_id)
            ->first();

        if (!$stock || $stock->quantite < $perte->quantite) {
            return back()->with('error', 'Stock insuffisant pour valider cette perte.');
        }

        DB::transaction(function () use ($perte, $stock) {
            $stock->decrement('quantite', $perte->quantite);
            $perte->update([
                'statut' => 'approved',
                'admin_id' => Auth::id(),
                'validated_at' => now(),
            ]);
        });

        return back()->with('success', 'Perte validée avec succès.');
    }

    public function rejectPerte(Request $request, Perte $perte)
    {
        $request->validate([
            'rejet_reason' => 'required|string|min:5',
        ]);

        if ($perte->statut !== 'pending') {
            return back()->with('error', 'Cette perte ne peut pas être rejetée.');
        }

        $perte->update([
            'statut' => 'rejected',
            'admin_id' => Auth::id(),
            'rejet_reason' => $request->rejet_reason,
            'validated_at' => now(),
        ]);

        return back()->with('success', 'Perte rejetée avec succès.');
    }
}
