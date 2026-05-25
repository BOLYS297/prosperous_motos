<?php

namespace App\Http\Controllers\Magasinier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DepenseController extends Controller
{
    public function create()
    {
        $produits = \App\Models\Produit::orderBy('nom')->get();
        return view('magasinier.depenses.create', compact('produits'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'produit_id' => 'required|exists:produits,id',
            'quantite' => 'required|integer|min:1',
            'raison' => 'required|string',
        ]);

        $boutiqueId = \Illuminate\Support\Facades\Auth::user()->boutique_id;

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $boutiqueId) {
            \App\Models\Perte::create([
                'boutique_id' => $boutiqueId,
                'produit_id' => $request->produit_id,
                'user_id' => \Illuminate\Support\Facades\Auth::id(),
                'quantite' => $request->quantite,
                'raison' => $request->raison,
                'statut' => 'pending',
            ]);
        });

        return redirect()->route('magasinier.dashboard')->with('success', 'Perte soumise pour validation admin. Elle sera enregistrée définitivement après validation.');
    }
}
