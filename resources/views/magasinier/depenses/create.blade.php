@extends('layouts.magasinier')

@section('content')
<div class="mb-8">
    <a href="{{ route('magasinier.dashboard') }}" class="text-emerald-200 hover:text-white transition-colors flex items-center text-sm mb-4">
        <i class="ri-arrow-left-line mr-1"></i> Retour au tableau de bord
    </a>
    <h2 class="text-3xl font-bold text-primary mb-2 tracking-tight">Déclarer une perte de stock</h2>
</div>

<div class="glass-panel rounded-2xl p-8 max-w-3xl">
    <form action="{{ route('magasinier.depenses.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-sm mb-6 flex">
                <i class="ri-information-line text-xl mr-3"></i>
                <p>En déclarant une perte, la quantité sera <strong>immédiatement déduite de votre stock local</strong>. L'administrateur sera notifié et pourra valider ou rejeter la perte.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Produit concerné <span class="text-red-500">*</span></label>
                    <select name="produit_id" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-emerald-500 outline-none" required>
                        <option value="">-- Sélectionner un produit --</option>
                        @foreach($produits as $produit)
                            <option value="{{ $produit->id }}">{{ $produit->nom }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Quantité perdue (pièces) <span class="text-red-500">*</span></label>
                    <input type="number" name="quantite" min="1" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-emerald-500 outline-none" placeholder="Ex: 2" required>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Raison / Motif de la perte <span class="text-red-500">*</span></label>
                    <textarea name="raison" rows="4" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-emerald-500 outline-none" placeholder="Ex: Produit cassé lors du déchargement..." required></textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end border-t border-white/50 pt-6">
            <button type="submit" :class="type === 'perte' ? 'from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700' : 'from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700'" class="px-8 py-3 bg-gradient-to-r text-yellow font-bold rounded-xl shadow-lg transition-all transform hover:-translate-y-0.5 flex items-center">
                <i class="ri-check-line mr-2"></i> Enregistrer
            </button>
        </div>
    </form>
</div>
@endsection
