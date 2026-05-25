<?php

namespace App\Http\Controllers\Magasinier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DemandeTransfert;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransfertController extends Controller
{
    public function index()
    {
        $magasinId = Auth::user()->boutique_id;

        $demandes = DemandeTransfert::with(['produit', 'boutique'])
            ->orderByRaw("FIELD(statut, 'en_attente', 'probleme', 'expediee', 'livree')")
            ->orderBy('created_at', 'desc')
            ->get();

        return view('magasinier.transferts.index', compact('demandes'));
    }

    public function expedier(Request $request, $id)
    {
        $request->validate([
            'quantite_expediee' => 'required|integer|min:1',
        ]);

        $demande = DemandeTransfert::findOrFail($id);
        $magasinId = Auth::user()->boutique_id;

        if ($demande->statut !== 'en_attente') {
            return back()->with('error', 'Cette demande a déjà été traitée.');
        }

        // Check stock in Magasin
        $stockMagasin = Stock::where('boutique_id', $magasinId)
            ->where('produit_id', $demande->produit_id)
            ->first();

        if (!$stockMagasin || $stockMagasin->quantite < $request->quantite_expediee) {
            return back()->with('error', 'Stock insuffisant dans le magasin pour expédier cette quantité.');
        }

        DB::transaction(function () use ($demande, $request, $stockMagasin) {
            // Déduire du magasin
            $stockMagasin->decrement('quantite', $request->quantite_expediee);

            // Mettre à jour la demande
            $demande->update([
                'quantite_expediee' => $request->quantite_expediee,
                'statut' => 'expediee',
            ]);
        });

        return back()->with('success', 'Produits expédiés vers la boutique.');
    }
}
