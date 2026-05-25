<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class AchatDepenseNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $boutiqueNom,
        protected float $montant,
        protected int $achatId,
        protected string $adminNom
    ) {}

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $intitule = "Dépense pour Achat #{$this->achatId} (produits)";
        $actionUrl = route('boutiquier.depenses.create') . '?intitule=' . urlencode($intitule) . '&montant=' . urlencode($this->montant) . '&achat_id=' . urlencode($this->achatId);

        return [
            'message' => "L'administrateur $this->adminNom a proposé un paiement de " . number_format($this->montant, 0, ',', ' ') . " FCFA pour votre boutique '{$this->boutiqueNom}' (Achat #{$this->achatId}). Merci d'enregistrer cette somme en dépense pour validation.",
            'boutique' => $this->boutiqueNom,
            'montant' => $this->montant,
            'achat_id' => $this->achatId,
            'admin' => $this->adminNom,
            'prefill' => [
                'intitule' => $intitule,
                'montant' => $this->montant,
                'achat_id' => $this->achatId,
            ],
            'action_url' => $actionUrl,
        ];
    }

    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }
}
