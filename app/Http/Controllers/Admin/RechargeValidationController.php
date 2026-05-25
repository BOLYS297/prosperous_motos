<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RechargeValidationController extends Controller
{
    public function index()
    {
        $recharges = \App\Models\Recharge::with(['fournisseur', 'lignes.produit', 'destination'])
            ->whereIn('statut', ['confirmee_par_magasinier', 'anomalie'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.recharges.validation-list', compact('recharges'));
    }

    public function show(\App\Models\Recharge $recharge)
    {
        $recharge->load(['fournisseur', 'lignes.produit', 'destination', 'justificatifs']);
        return view('admin.recharges.validation-show', compact('recharge'));
    }

    public function valider(Request $request, \App\Models\Recharge $recharge)
    {
        $recharge->load('lignes');

        \Illuminate\Support\Facades\DB::transaction(function () use ($recharge) {
            foreach ($recharge->lignes as $ligne) {
                if ($ligne->quantite_recue > 0) {
                    $stock = \App\Models\Stock::firstOrCreate(
                        ['boutique_id' => $recharge->destination_id, 'produit_id' => $ligne->produit_id],
                        ['quantite' => 0]
                    );
                    $stock->increment('quantite', $ligne->quantite_recue);
                }
            }

            $recharge->update(['statut' => 'approuvee']);
        });

        return redirect()->route('admin.recharges.validation.index')->with('success', 'Recharge approuvée et stock mis à jour.');
    }

    public function rejeter(Request $request, \App\Models\Recharge $recharge)
    {
        $request->validate([
            'raison_rejet' => 'required|string|max:500',
        ]);

        $recharge->update([
            'statut' => 'rejetee',
            'raison_rejet' => $request->input('raison_rejet'),
        ]);

        return redirect()->route('admin.recharges.validation.index')->with('success', 'Recharge rejetée.');
    }
}
