<?php

namespace App\Http\Controllers\Boutiquier;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $boutiqueId = $user->boutique_id;
        $boutique = \App\Models\Boutique::find($boutiqueId);

        if (!$boutique) {
            $produits = collect();
        } else {
            // Produits avec leur stock local (sans révéler la quantité exacte)
            $produits = \App\Models\Produit::with(['stocks' => function ($query) use ($boutiqueId) {
                $query->where('boutique_id', $boutiqueId);
            }])->orderBy('nom')->get();
        }

        $grossistes = \App\Models\Grossiste::with('prixProduits')->get();

        // Ventes du jour
        $ventesAujourdhui = \App\Models\Vente::where('boutique_id', $boutiqueId)
            ->whereDate('created_at', today())
            ->sum('montant_total');

        $nbVentesJour = \App\Models\Vente::where('boutique_id', $boutiqueId)
            ->whereDate('created_at', today())
            ->count();

        $dettes = \App\Models\Achat::with('paiements')
            ->where('statut', 'dette')
            ->get()
            ->filter(fn($achat) => $achat->reste_a_payer > 0);

        $dettesCount = $dettes->count();
        $dettesRestantes = $dettes->sum(fn($achat) => $achat->reste_a_payer);
        $notifications = $user->unreadNotifications;

        return view('boutiquier.dashboard', compact('boutique', 'produits', 'grossistes', 'ventesAujourdhui', 'nbVentesJour', 'dettesCount', 'dettesRestantes', 'notifications'));
    }
}
