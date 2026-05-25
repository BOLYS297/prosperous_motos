<?php

namespace App\Http\Controllers\Boutiquier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DemandeTransfert;
use App\Models\Produit;
use App\Models\Stock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DemandeTransfertController extends Controller
{
    public function index()
    {
        $boutiqueId = Auth::user()->boutique_id;
        $demandes = DemandeTransfert::with('produit')
            ->where('boutique_id', $boutiqueId)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('boutiquier.transferts.index', compact('demandes'));
    }

    public function create()
    {
        $produits = Produit::orderBy('nom')->get();
        return view('boutiquier.transferts.create', compact('produits'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'produit_id' => 'required|exists:produits,id',
            'quantite_demandee' => 'required|integer|min:1',
        ]);

        DemandeTransfert::create([
            'boutique_id' => Auth::user()->boutique_id,
            'produit_id' => $request->produit_id,
            'quantite_demandee' => $request->quantite_demandee,
            'statut' => 'en_attente',
        ]);

        return redirect()->route('boutiquier.transferts.index')->with('success', 'Demande de stock envoyée au magasin central.');
    }

    public function confirmer(Request $request, $id)
    {
        $demande = DemandeTransfert::where('boutique_id', Auth::user()->boutique_id)->findOrFail($id);

        if ($demande->statut !== 'expediee') {
            return back()->with('error', 'Vous ne pouvez confirmer qu\'une demande expédiée.');
        }

        DB::transaction(function () use ($demande) {
            $demande->update(['statut' => 'livree']);

            // Ajouter le stock à la boutique
            $stock = Stock::firstOrCreate(
                ['boutique_id' => $demande->boutique_id, 'produit_id' => $demande->produit_id],
                ['quantite' => 0]
            );
            $stock->increment('quantite', $demande->quantite_expediee);
        });

        return back()->with('success', 'Réception confirmée ! Le stock a été ajouté à votre boutique.');
    }


    public function signalerProbleme(Request $request, $id)
    {
        $request->validate([
            'note_probleme' => 'required|string|max:500',
        ]);

        $demande = DemandeTransfert::where('boutique_id', Auth::user()->boutique_id)->findOrFail($id);

        if ($demande->statut !== 'expediee') {
            return back()->with('error', 'Vous ne pouvez signaler un problème que sur une demande expédiée.');
        }

        $demande->update([
            'statut' => 'probleme',
            'note_probleme' => $request->note_probleme,
        ]);

        return back()->with('success', 'Problème signalé au magasin central.');
    }
}
