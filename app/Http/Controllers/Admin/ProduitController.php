<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grossiste;
use App\Models\PrixGrossiste;
use Illuminate\Http\Request;

class ProduitController extends Controller
{
    public function index()
    {
        $produits = \App\Models\Produit::all();
        return view('admin.produits.index', compact('produits'));
    }

    public function create()
    {
        $grossistes = Grossiste::all();
        return view('admin.produits.create', compact('grossistes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'prix_achat' => 'required|numeric|min:0',
            'prix_vente' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'prix_grossiste' => 'nullable|array',
            'prix_grossiste.*.grossiste_id' => 'nullable|exists:grossistes,id',
            'prix_grossiste.*.prix_achat' => 'nullable|numeric|min:0',
            'prix_grossiste.*.prix_vente' => 'nullable|numeric|min:0',
        ]);

        $data = $request->only(['nom', 'prix_achat', 'prix_vente']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('produits', 'public');
        }

        $produit = \App\Models\Produit::create($data);

        foreach ($request->input('prix_grossiste', []) as $prix) {
            $grossisteId = $prix['grossiste_id'] ?? null;
            $prixAchat = $prix['prix_achat'] ?? null;
            $prixVente = $prix['prix_vente'] ?? null;

            $hasPrixAchat = $prixAchat !== null && $prixAchat !== '';
            $hasPrixVente = $prixVente !== null && $prixVente !== '';

            if (!$grossisteId && !$hasPrixAchat && !$hasPrixVente) {
                continue;
            }

            if (!$grossisteId || !$hasPrixAchat || !$hasPrixVente) {
                return back()->withErrors(['prix_grossiste' => 'Veuillez fournir à la fois le grossiste, le prix d\'achat et le prix de vente pour chaque ligne.'])->withInput();
            }

            PrixGrossiste::updateOrCreate(
                [
                    'grossiste_id' => $grossisteId,
                    'produit_id' => $produit->id,
                ],
                [
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente,
                ]
            );
        }

        return redirect()->route('admin.produits.index')->with('success', 'Produit ajouté au catalogue avec succès.');
    }

    public function edit(\App\Models\Produit $produit)
    {
        $grossistes = Grossiste::all();
        $produit->load('prixGrossistes');
        return view('admin.produits.edit', compact('produit', 'grossistes'));
    }

    public function update(Request $request, \App\Models\Produit $produit)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'prix_achat' => 'required|numeric|min:0',
            'prix_vente' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'prix_grossiste' => 'nullable|array',
            'prix_grossiste.*.grossiste_id' => 'nullable|exists:grossistes,id',
            'prix_grossiste.*.prix_achat' => 'nullable|numeric|min:0',
            'prix_grossiste.*.prix_vente' => 'nullable|numeric|min:0',
        ]);

        $data = $request->only(['nom', 'prix_achat', 'prix_vente']);

        if ($request->hasFile('image')) {
            if ($produit->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($produit->image)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($produit->image);
            }
            $data['image'] = $request->file('image')->store('produits', 'public');
        }

        $produit->update($data);

        foreach ($request->input('prix_grossiste', []) as $prix) {
            $grossisteId = $prix['grossiste_id'] ?? null;
            $prixAchat = $prix['prix_achat'] ?? null;
            $prixVente = $prix['prix_vente'] ?? null;

            $hasPrixAchat = $prixAchat !== null && $prixAchat !== '';
            $hasPrixVente = $prixVente !== null && $prixVente !== '';

            if (!$grossisteId && !$hasPrixAchat && !$hasPrixVente) {
                continue;
            }

            if (!$grossisteId || !$hasPrixAchat || !$hasPrixVente) {
                return back()->withErrors(['prix_grossiste' => 'Veuillez fournir à la fois le grossiste, le prix d\'achat et le prix de vente pour chaque ligne.'])->withInput();
            }

            PrixGrossiste::updateOrCreate(
                [
                    'grossiste_id' => $grossisteId,
                    'produit_id' => $produit->id,
                ],
                [
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente,
                ]
            );
        }

        return redirect()->route('admin.produits.index')->with('success', 'Produit modifié avec succès.');
    }

    public function destroy(\App\Models\Produit $produit)
    {
        $produit->delete();
        return redirect()->route('admin.produits.index')->with('success', 'Produit supprimé du catalogue.');
    }
}
