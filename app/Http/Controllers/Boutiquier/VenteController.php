<?php

namespace App\Http\Controllers\Boutiquier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VenteController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'produit_id' => 'required|exists:produits,id',
            'quantite' => 'required|integer|min:1',
            'is_grossiste' => 'nullable|boolean',
            'grossiste_id' => 'nullable|exists:grossistes,id',
        ]);

        $user = Auth::user();
        $boutiqueId = $user->boutique_id;

        $stock = \App\Models\Stock::where('boutique_id', $boutiqueId)
            ->where('produit_id', $request->produit_id)
            ->first();

        if (!$stock || $stock->quantite < $request->quantite) {
            return back()->with('error', 'Stock insuffisant pour ce produit.');
        }

        $produit = \App\Models\Produit::findOrFail($request->produit_id);
        $isGrossiste = $request->boolean('is_grossiste');
        $grossisteId = $isGrossiste ? $request->grossiste_id : null;
        $unitPrice = $produit->prix_vente;

        if ($isGrossiste) {
            if (!$grossisteId) {
                return back()->with('error', 'Veuillez sélectionner un grossiste pour cette vente.');
            }

            $prixGrossiste = \App\Models\PrixGrossiste::where('grossiste_id', $grossisteId)
                ->where('produit_id', $produit->id)
                ->first();

            if (!$prixGrossiste) {
                return back()->with('error', 'Aucun tarif grossiste défini pour ce produit.');
            }

            $unitPrice = $prixGrossiste->prix_vente;
        }

        $total = $unitPrice * $request->quantite;

        DB::transaction(function () use ($request, $user, $boutiqueId, $produit, $total, $unitPrice, $stock, $grossisteId, $isGrossiste) {
            $vente = \App\Models\Vente::create([
                'boutique_id' => $boutiqueId,
                'user_id' => $user->id,
                'montant_total' => $total,
                'grossiste_id' => $grossisteId,
            ]);

            \App\Models\VenteLigne::create([
                'vente_id' => $vente->id,
                'produit_id' => $produit->id,
                'quantite' => $request->quantite,
                'prix_unitaire' => $unitPrice,
                'est_grossiste' => $isGrossiste,
            ]);

            $stock->decrement('quantite', $request->quantite);

            $boutique = \App\Models\Boutique::find($boutiqueId);
            if ($boutique) {
                $boutique->increment('solde', $total);
            }
        });

        return back()->with('success', 'Vente enregistrée ! ' . $request->quantite . 'x ' . $produit->nom . ' = ' . number_format($total, 0, ',', ' ') . ' FCFA');
    }

    public function historique()
    {
        $boutiqueId = Auth::user()->boutique_id;

        $ventes = \App\Models\Vente::with(['lignes.produit', 'user'])
            ->where('boutique_id', $boutiqueId)
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->get();

        $totalJour = $ventes->sum('montant_total');

        return view('boutiquier.historique', compact('ventes', 'totalJour'));
    }
}
