<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vente;
use App\Notifications\SyncConflictNotification;
use Illuminate\Support\Facades\Notification;

class SyncConflictHandler
{
    /**
     * Notify admin users about a sync conflict on a vente.
     */
    public function notifyConflict(Vente $vente, string $reason): void
    {
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();

        if ($admins->isEmpty()) {
            return;
        }

        $vente->load(['user', 'boutique', 'lignes.produit']);

        $produitNames = $vente->lignes->map(fn($l) => $l->produit?->nom ?? 'Inconnu')->implode(', ');

        Notification::send($admins, new SyncConflictNotification(
            'Conflit de synchronisation détecté',
            "Une vente offline (#{$vente->id}) par {$vente->user->nom_utilisateur} présente un conflit : {$reason}. Produits: {$produitNames}.",
            'Voir le tableau de bord',
            route('admin.dashboard')
        ));
    }
}
