@extends('layouts.admin')

@section('content')
<div class="mb-8 flex justify-between items-center">
    <div>
        <a href="{{ route('admin.achats.index') }}" class="text-blue-200 hover:text-white transition-colors flex items-center text-sm mb-4">
            <i class="ri-arrow-left-line mr-1"></i> Retour à l'historique
        </a>
        <h2 class="text-3xl font-bold text-white tracking-tight">Détails de l'Achat #{{ str_pad($achat->id, 4, '0', STR_PAD_LEFT) }}</h2>
    </div>
    <button onclick="window.print()" class="px-4 py-2 bg-white/20 text-white rounded-lg hover:bg-white/30 transition-colors flex items-center backdrop-blur-md">
        <i class="ri-printer-line mr-2"></i> Imprimer
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="glass-panel p-6 rounded-2xl">
        <div class="text-sm text-slate-500 mb-1">Fournisseur</div>
        <div class="font-bold text-lg text-slate-800">{{ $achat->fournisseur ? $achat->fournisseur->nom : 'N/A' }}</div>
        @if($achat->fournisseur && $achat->fournisseur->contact)
            <div class="text-sm text-slate-600 mt-1"><i class="ri-phone-line"></i> {{ $achat->fournisseur->contact }}</div>
        @endif
    </div>

    <div class="glass-panel p-6 rounded-2xl">
        <div class="text-sm text-slate-500 mb-1">Informations</div>
        <div class="font-medium text-slate-800"><i class="ri-calendar-line"></i> {{ $achat->created_at->format('d/m/Y à H:i') }}</div>
        <div class="text-sm text-slate-600 mt-1"><i class="ri-store-2-line"></i> Dest: {{ $achat->boutique ? $achat->boutique->nom : 'N/A' }}</div>
    </div>

    <div class="glass-panel p-6 rounded-2xl flex flex-col justify-center items-center text-center {{ $achat->statut === 'paye' ? 'border-b-4 border-emerald-500' : 'border-b-4 border-rose-500' }}">
        <div class="text-sm text-slate-500 mb-1">Statut Paiement</div>
        @if($achat->statut === 'paye')
            <div class="font-bold text-2xl text-emerald-600 flex items-center"><i class="ri-check-double-line mr-2"></i> PAYÉ</div>
        @else
            <div class="font-bold text-2xl text-rose-600 flex items-center"><i class="ri-time-line mr-2"></i> DETTE</div>
        @endif
    </div>
</div>

<div class="glass-panel rounded-2xl overflow-hidden mb-8">
    <div class="p-6 bg-white/50 border-b border-slate-200/50">
        <h3 class="font-bold text-slate-800">Produits achetés</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-white/40 border-b border-slate-200/50 text-sm text-slate-600">
                    <th class="p-4 font-semibold">Produit</th>
                    <th class="p-4 font-semibold text-center">Quantité</th>
                    <th class="p-4 font-semibold text-right">Prix Unitaire</th>
                    <th class="p-4 font-semibold text-right">Total</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                @foreach($achat->lignes as $ligne)
                    <tr class="border-b border-white/20 hover:bg-white/30">
                        <td class="p-4 font-medium text-slate-800">
                            {{ $ligne->produit ? $ligne->produit->nom : 'Produit inconnu' }}
                        </td>
                        <td class="p-4 text-center text-slate-600 font-bold">
                            {{ $ligne->quantite }}
                        </td>
                        <td class="p-4 text-right text-slate-600">
                            {{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="p-4 text-right font-bold text-slate-800">
                            {{ number_format($ligne->prix_unitaire * $ligne->quantite, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="bg-slate-50/50">
                    <td colspan="3" class="p-4 text-right font-medium text-slate-600 uppercase text-xs tracking-wider">
                        Montant Total de la facture
                    </td>
                    <td class="p-4 text-right font-bold text-2xl text-slate-800">
                        {{ number_format($achat->montant_total, 0, ',', ' ') }} FCFA
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
@endsection
