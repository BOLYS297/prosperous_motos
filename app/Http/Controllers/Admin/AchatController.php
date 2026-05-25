<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\AchatDepenseNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AchatController extends Controller
{
    public function index()
    {
        $achats = \App\Models\Achat::with(['fournisseur', 'boutique', 'lignes.produit'])->orderBy('created_at', 'desc')->get();
        return view('admin.achats.index', compact('achats'));
    }

    public function create()
    {
        $fournisseurs = \App\Models\Fournisseur::all();
        $produits = \App\Models\Produit::all();
        // Destination finale de tout achat admin : le magasin uniquement.
        $magasins = \App\Models\Boutique::where('type', 'magasin')->get();
        // Liste complète des boutiques utilisables pour le débit (trésorerie)
        $allBoutiques = \App\Models\Boutique::orderBy('nom')->get();

        return view('admin.achats.create', compact('fournisseurs', 'produits', 'magasins', 'allBoutiques'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fournisseur_id' => 'required|exists:fournisseurs,id',
            'boutique_id' => [
                'required',
                'exists:boutiques,id',
                function ($attribute, $value, $fail) {
                    $boutique = \App\Models\Boutique::find($value);
                    if (! $boutique || $boutique->type !== 'magasin') {
                        $fail('La destination doit être un magasin.');
                    }
                },
            ],
            'debit_boutique_id' => 'required_if:statut,paye|exists:boutiques,id',
            'statut' => 'required|in:paye,dette',
            'lignes' => 'required|array|min:1',
            'lignes.*.produit_id' => 'required|exists:produits,id',
            'lignes.*.quantite' => 'required|integer|min:1',
            'lignes.*.prix_unitaire' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            $montant_total = 0;

            foreach ($request->lignes as $ligne) {
                $montant_total += $ligne['quantite'] * $ligne['prix_unitaire'];
            }

            $achat = \App\Models\Achat::create([
                'fournisseur_id' => $request->fournisseur_id,
                'boutique_id' => $request->boutique_id,
                'statut' => $request->statut,
                'montant_total' => $montant_total
            ]);

            $destination = \App\Models\Boutique::find($request->boutique_id);

            foreach ($request->lignes as $ligne) {
                \App\Models\AchatLigne::create([
                    'achat_id' => $achat->id,
                    'produit_id' => $ligne['produit_id'],
                    'quantite' => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prix_unitaire'],
                ]);

                // If destination is not a magasin, increment stock immediately.
                if (! $destination || $destination->type !== 'magasin') {
                    $stock = \App\Models\Stock::firstOrCreate(
                        ['boutique_id' => $request->boutique_id, 'produit_id' => $ligne['produit_id']],
                        ['quantite' => 0]
                    );

                    $stock->increment('quantite', $ligne['quantite']);
                }
            }

            // Si c'est payé, on déduit le montant du solde de la boutique choisie et on notifie le boutiquier présent.
            if ($request->statut === 'paye') {
                // Enregistrer le paiement mais ne pas décrémenter le solde immédiatement.
                // La boutique choisie devra enregistrer une dépense et l'admin validera ensuite.
                $debitBoutique = \App\Models\Boutique::find($request->debit_boutique_id);
                if ($debitBoutique) {
                    \App\Models\AchatPaiement::create([
                        'achat_id' => $achat->id,
                        'boutique_id' => $debitBoutique->id,
                        'user_id' => Auth::id(),
                        'montant' => $montant_total,
                        'description' => 'Proposition de paiement comptant pour l\'achat #' . $achat->id,
                    ]);

                    // Notifier le(s) boutiquier(s) présents dans la boutique choisie
                    $this->notifyBoutiquiers($debitBoutique, $montant_total, $achat);
                }
            }

            // If destination is a magasin, always create a Recharge record for magasinier validation
            if ($destination && $destination->type === 'magasin') {
                $recharge = \App\Models\Recharge::create([
                    'source_id' => null,
                    'destination_id' => $request->boutique_id,
                    'user_id' => \Illuminate\Support\Facades\Auth::id(),
                    'montant' => $montant_total,
                    'statut' => 'en_attente',
                    'fournisseur_id' => $request->fournisseur_id,
                ]);

                foreach ($request->lignes as $ligne) {
                    \App\Models\RechargeLigne::create([
                        'recharge_id' => $recharge->id,
                        'produit_id' => $ligne['produit_id'],
                        'quantite_envoyee' => $ligne['quantite'],
                        'quantite_recue' => 0,
                        'quantite_manquante' => $ligne['quantite'],
                    ]);
                }
            }
        });

        return redirect()->route('admin.achats.index')->with('success', 'Achat enregistré avec succès et la boutique de trésorerie a été débitée.');
    }

    private function notifyBoutiquiers(\App\Models\Boutique $boutique, float $montant, \App\Models\Achat $achat)
    {
        $currentHour = (int) now()->format('H');
        $shift = null;

        if ($currentHour >= 7 && $currentHour < 17) {
            $shift = 'matin';
        } elseif ($currentHour >= 17 && $currentHour < 23) {
            $shift = 'soir';
        }

        $query = \App\Models\User::where('role', 'boutiquier')->where('boutique_id', $boutique->id);
        $presentBoutiquiers = $shift ? (clone $query)->where('shift', $shift)->get() : collect();
        $recipients = $presentBoutiquiers->isNotEmpty() ? $presentBoutiquiers : $query->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AchatDepenseNotification(
                $boutique->nom,
                $montant,
                $achat->id,
                Auth::user()->nom_utilisateur ?? 'Administrateur'
            ));
        }
    }

    public function show(\App\Models\Achat $achat)
    {
        $achat->load(['fournisseur', 'boutique', 'lignes.produit']);
        return view('admin.achats.show', compact('achat'));
    }
}
