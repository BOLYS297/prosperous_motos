@extends('layouts.admin')

@section('content')
<div class="mb-8">
    <a href="{{ route('admin.users.index') }}" class="text-blue-200 hover:text-white transition-colors flex items-center text-sm mb-4">
        <i class="ri-arrow-left-line mr-1"></i> Retour à la liste
    </a>
    <h2 class="text-3xl font-bold text-primary mb-2 tracking-tight">Ajouter un Employé</h2>
</div>

<div class="glass-panel rounded-2xl p-8 max-w-3xl">
    <form action="{{ route('admin.users.store') }}" method="POST">
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

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Nom d'utilisateur</label>
                <input type="text" name="nom_utilisateur" value="{{ old('nom_utilisateur') }}" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Adresse Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none" required>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-slate-700 mb-2">Mot de passe</label>
            <input type="text" name="password" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Ex: Boutiquier2026" required>
            <p class="text-xs text-slate-500 mt-1">Le mot de passe est affiché en clair pour que vous puissiez le communiquer à l'employé.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8" x-data="{ role: 'boutiquier' }">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Rôle</label>
                <select name="role" x-model="role" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none" required>
                    <option value="boutiquier">Boutiquier</option>
                    <option value="magasinier">Magasinier</option>
                </select>
            </div>

            <div x-show="role === 'boutiquier'">
                <label class="block text-sm font-medium text-slate-700 mb-2">Shift Horaire</label>
                <select name="shift" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="matin">Matin (07h - 17h)</option>
                    <option value="soir">Soir (17h - 22h)</option>
                </select>
            </div>

            <div x-show="role === 'boutiquier' || role === 'magasinier'" class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-700 mb-2">Assignation Boutique/Magasin</label>
                <select name="boutique_id" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">-- Sélectionner une boutique --</option>
                    @foreach($boutiques as $boutique)
                        <option value="{{ $boutique->id }}">{{ $boutique->nom }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-2"><i class="ri-information-line"></i> Optionnel lors de la création. Vous pouvez assigner la boutique plus tard.</p>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-medium rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all shadow-lg transform hover:-translate-y-0.5">
                Enregistrer l'employé
            </button>
        </div>
    </form>
</div>
@endsection
