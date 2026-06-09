@extends('layouts.admin')

@section('content')
<div class="mb-8">
    <a href="{{ route('admin.achats.index') }}" class="text-blue-200 hover:text-white transition-colors flex items-center text-sm mb-4">
        <i class="ri-arrow-left-line mr-1"></i> Retour à l'historique
    </a>
    <h2 class="text-3xl font-bold text-primary mb-2 tracking-tight">Enregistrer un Achat</h2>
</div>

<div class="glass-panel rounded-2xl p-8" x-data="achatForm()" @mount="init()">
    <form action="{{ route('admin.achats.store') }}" method="POST">
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

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 pb-8 border-b border-slate-200/50">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Fournisseur <span class="text-red-500">*</span></label>
                <select name="fournisseur_id" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none" required>
                    <option value="">-- Sélectionner --</option>
                    @foreach($fournisseurs as $fournisseur)
                        <option value="{{ $fournisseur->id }}">{{ $fournisseur->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Magasin de destination <span class="text-red-500">*</span></label>
                <select name="boutique_id" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none" required>
                    <option value="">-- Sélectionner un magasin --</option>
                    @foreach($magasins as $boutique)
                        <option value="{{ $boutique->id }}">{{ $boutique->nom }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-2">La destination finale de cet achat est toujours un magasin.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Boutique à débiter <span class="text-red-500">*</span> (comptant uniquement)</label>
                <select name="debit_boutique_id" id="debit_boutique_select" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none">
                    <option value="">-- Sélectionner une boutique --</option>
                    @foreach($allBoutiques as $boutique)
                        <option value="{{ $boutique->id }}">{{ $boutique->nom }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-2">Le solde de cette boutique sera utilisé pour régler l'achat comptant. (Pas nécessaire pour les dettes)</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Statut du paiement <span class="text-red-500">*</span></label>
                <select name="statut" x-model="statut" @change="toggleDebitField()" class="w-full px-4 py-3 border border-slate-300 rounded-xl bg-white/50 focus:ring-2 focus:ring-blue-500 outline-none" required>
                    <option value="paye">Payé comptant (déduit du solde)</option>
                    <option value="dette">Achat à crédit (Dette partagée par toutes les boutiques)</option>
                </select>
            </div>
        </div>

        <div class="mb-6 flex justify-between items-end">
            <h3 class="text-lg font-bold text-slate-800">Lignes d'achat (Produits)</h3>
            <button type="button" @click="addLine()" class="px-4 py-2 bg-slate-100 text-blue-600 font-medium rounded-lg hover:bg-blue-50 transition-colors text-sm">
                <i class="ri-add-line"></i> Ajouter une ligne
            </button>
        </div>

        <!-- Les achats administrateur sont automatiquement créés comme recharges destinées aux magasins. -->

        <div class="space-y-4 mb-8">
            <template x-for="(ligne, index) in lignes" :key="index">
                <div class="flex items-end gap-4 p-4 bg-white/40 border border-slate-200 rounded-xl transition-all">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Produit</label>
                        <select :name="`lignes[${index}][produit_id]`" x-model="ligne.produit_id" @change="updatePrice(index)" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none text-sm" required>
                            <option value="">Sélectionner un produit</option>
                            <template x-for="p in produits" :key="p.id">
                                <option :value="p.id" x-text="p.nom"></option>
                            </template>
                        </select>
                    </div>
                    <div class="w-32">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Prix Unitaire</label>
                        <input type="number" step="0.01" :name="`lignes[${index}][prix_unitaire]`" x-model="ligne.prix_unitaire" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none text-sm" required>
                    </div>
                    <div class="w-24">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Qté</label>
                        <input type="number" min="1" :name="`lignes[${index}][quantite]`" x-model="ligne.quantite" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 outline-none text-sm" required>
                    </div>
                    <div class="w-32">
                        <label class="block text-xs font-medium text-slate-500 mb-1">Total</label>
                        <div class="px-3 py-2 bg-slate-100 rounded-lg text-sm font-bold text-slate-700 text-right" x-text="(ligne.prix_unitaire * ligne.quantite).toLocaleString() + ' F'"></div>
                    </div>
                    <div>
                        <button type="button" @click="removeLine(index)" class="p-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition-colors" x-show="lignes.length > 1">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <div class="flex justify-between items-center bg-slate-800 text-white p-6 rounded-xl mb-8 shadow-lg">
            <span class="text-lg font-medium text-slate-300">Montant Total de la facture</span>
            <span class="text-3xl font-bold" x-text="calculateTotal().toLocaleString() + ' FCFA'"></span>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold rounded-xl hover:from-blue-700 hover:to-indigo-700 transition-all shadow-xl transform hover:-translate-y-1 text-lg flex items-center">
                <i class="ri-save-3-line mr-2"></i> Valider l'achat
            </button>
        </div>
    </form>
</div>

<script>
    function achatForm() {
        return {
            produits: @json($produits),
            statut: 'paye',
            lignes: [
                { produit_id: '', prix_unitaire: 0, quantite: 1 }
            ],
            init() {
                // Initialiser la visibilité du champ debit_boutique_id au chargement
                this.toggleDebitField();
            },
            toggleDebitField() {
                const debitSelect = document.getElementById('debit_boutique_select');
                if (this.statut === 'paye') {
                    debitSelect.setAttribute('required', 'required');
                    debitSelect.closest('div').style.display = 'block';
                } else {
                    debitSelect.removeAttribute('required');
                    debitSelect.closest('div').style.display = 'none';
                    debitSelect.value = '';
                }
            },
            addLine() {
                this.lignes.push({ produit_id: '', prix_unitaire: 0, quantite: 1 });
            },
            removeLine(index) {
                if(this.lignes.length > 1) {
                    this.lignes.splice(index, 1);
                }
            },
            updatePrice(index) {
                const produitId = parseInt(this.lignes[index].produit_id);
                const produit = this.produits.find(p => p.id === produitId);
                if(produit) {
                    this.lignes[index].prix_unitaire = produit.prix_achat;
                }
            },
            calculateTotal() {
                return this.lignes.reduce((total, ligne) => {
                    return total + (parseFloat(ligne.prix_unitaire || 0) * parseInt(ligne.quantite || 0));
                }, 0);
            }
        }
    }
</script>
@endsection
