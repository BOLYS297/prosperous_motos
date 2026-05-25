@extends('layouts.admin')

@section('content')
<div class="mb-8">
    <h2 class="text-3xl font-bold text-primary mb-2 tracking-tight">Vue d'ensemble</h2>
    <p class="text-black">Statistiques financières et activités récentes.</p>
</div>

@if(!empty($rechargesEnAttente) && $rechargesEnAttente > 0)
    <div class="mb-8 glass-panel rounded-2xl p-6 bg-gradient-to-r from-amber-50 to-orange-50 border border-orange-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center mr-4">
                    <i class="ri-alert-line text-2xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800">{{ $rechargesEnAttente }} recharge(s) en attente de validation</h3>
                    <p class="text-sm text-slate-600">Les magasiniers ont confirmé des recharges qui nécessitent votre approbation.</p>
                </div>
            </div>
            <a href="{{ route('admin.recharges.validation.index') }}" class="px-6 py-2 bg-gradient-to-r from-orange-600 to-amber-600 text-white rounded-lg font-semibold hover:shadow-lg transition-all">
                Valider maintenant
            </a>
        </div>
    </div>
@endif

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

    <!-- Card 1: Solde Entreprise -->
    <div class="glass-panel rounded-2xl p-6 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <i class="ri-wallet-3-fill text-6xl text-blue-600"></i>
        </div>
        <h3 class="text-slate-500 font-medium text-sm mb-1 uppercase tracking-wider">Solde Total Entreprise</h3>
        <p class="text-3xl font-bold text-slate-800">{{ number_format($entrepriseSolde, 0, ',', ' ') }} <span class="text-xl text-slate-500 font-normal">FCFA</span></p>
        <div class="mt-4 flex items-center text-sm text-slate-500 font-medium">
            <i class="ri-arrow-up-line mr-1"></i> Total disponible sur toutes les boutiques
        </div>
    </div>

    <!-- Card 2: Boutique 1 -->
    <div class="glass-panel rounded-2xl p-6 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <i class="ri-store-2-fill text-6xl text-indigo-600"></i>
        </div>
        <h3 class="text-slate-500 font-medium text-sm mb-1 uppercase tracking-wider">{{ $topBoutique ? $topBoutique->nom : 'Boutique principale' }}</h3>
        <p class="text-3xl font-bold text-slate-800">{{ $topBoutique ? number_format($topBoutique->solde, 0, ',', ' ') : '0' }} <span class="text-xl text-slate-500 font-normal">FCFA</span></p>
        <div class="mt-4 flex items-center text-sm text-slate-500 font-medium">
            <i class="ri-arrow-up-line mr-1"></i> Meilleure situation financière
        </div>
    </div>

    <!-- Card 3: Boutique 2 -->
    <div class="glass-panel rounded-2xl p-6 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <i class="ri-store-3-fill text-6xl text-purple-600"></i>
        </div>
        <h3 class="text-slate-500 font-medium text-sm mb-1 uppercase tracking-wider">{{ $secondBoutique ? $secondBoutique->nom : 'Autre boutique' }}</h3>
        <p class="text-3xl font-bold text-slate-800">{{ $secondBoutique ? number_format($secondBoutique->solde, 0, ',', ' ') : '0' }} <span class="text-xl text-slate-500 font-normal">FCFA</span></p>
        <div class="mt-4 flex items-center text-sm text-slate-500 font-medium">
            <i class="ri-arrow-up-line mr-1"></i> Deuxième meilleure boutique
        </div>
    </div>

    <!-- Card 4: Dettes Fournisseurs -->
    <div class="glass-panel rounded-2xl p-6 relative overflow-hidden group">
        <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
            <i class="ri-hand-coin-fill text-6xl text-rose-600"></i>
        </div>
        <h3 class="text-slate-500 font-medium text-sm mb-1 uppercase tracking-wider">Dettes Fournisseurs</h3>
        <p class="text-3xl font-bold text-rose-600">{{ number_format($dettesFournisseurs, 0, ',', ' ') }} <span class="text-xl font-normal">FCFA</span></p>
        <div class="mt-4 flex items-center text-sm text-slate-500 font-medium">
            <i class="ri-time-line mr-1"></i> Montant restant à régler
        </div>
    </div>

</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Graphique (Placeholder) -->
    <div class="lg:col-span-2 glass-panel rounded-2xl p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-slate-800">Évolution des ventes</h3>
            <select class="bg-white border border-slate-200 text-sm rounded-lg px-3 py-2 text-slate-700 outline-none focus:border-blue-500">
                <option>Cette Semaine</option>
                <option>Ce Mois</option>
            </select>
        </div>
        <div class="h-64 flex items-end space-x-2 w-full pt-4">
            @foreach($weeklySales as $day)
                @php
                    $height = 20 + ($day['total'] / max($maxWeeklySales, 1)) * 80;
                @endphp
                <div class="w-full rounded-t-sm bg-blue-500 relative group hover:bg-blue-600 transition-colors cursor-pointer" style="height: {{ $height }}%;">
                    <span class="absolute -top-6 w-full text-center text-xs font-bold text-blue-600 opacity-0 group-hover:opacity-100 transition-opacity">{{ $day['label'] }}</span>
                    <span class="absolute bottom-2 left-1/2 -translate-x-1/2 text-xs text-slate-800 font-semibold">{{ number_format($day['total'], 0, ',', ' ') }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Activités récentes -->
    <div class="glass-panel rounded-2xl p-6">
        <h3 class="text-xl font-bold text-slate-800 mb-6">Activités Récentes</h3>
        <div class="space-y-4">
            @forelse($recentActivities as $activity)
                <div class="flex items-start">
                    <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                        <i class="ri-history-line"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-slate-800">{{ $activity->action_label }}</p>
                        <p class="text-xs text-slate-500">{{ $activity->description_label }}</p>
                        <p class="text-xs text-slate-400 mt-1">{{ $activity->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @empty
                <div class="text-sm text-slate-500">Aucune activité récente n'a encore été consignée.</div>
            @endforelse
        </div>
        <button class="w-full mt-6 py-2 text-sm text-blue-600 font-medium border border-blue-200 rounded-lg hover:bg-blue-50 transition-colors"><a href="{{ route('admin.logs.index') }}" class="text-blue-600 hover:text-blue-800" {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}>Voir tout l'historique</a></button>
    </div>
</div>
@endsection
