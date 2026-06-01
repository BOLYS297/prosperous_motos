<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DemandeTransfert;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OfflineTransfertController extends Controller
{
    /**
     * Process an offline transfert demand.
     */
    public function processTransfert(User $user, array $operation): array
    {
        $data = $operation['data'];
        $clientUuid = $operation['client_uuid'];

        // Check for duplicate (idempotence)
        $existing = DemandeTransfert::where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return [
                'status' => 'synced',
                'message' => 'Demande de transfert déjà synchronisée',
                'server_id' => $existing->id,
            ];
        }

        try {
            $demande = null;
            DB::transaction(function () use ($data, $clientUuid, $user, &$demande) {
                $demande = DemandeTransfert::create([
                    'boutique_id' => $user->boutique_id,
                    'produit_id' => $data['produit_id'],
                    'quantite_demandee' => $data['quantite_demandee'] ?? 1,
                    'statut' => 'en_attente',
                    'client_uuid' => $clientUuid,
                    'synced_at' => now(),
                    'is_offline' => true,
                ]);
            });

            return [
                'status' => 'synced',
                'message' => 'Demande de transfert synchronisée avec succès',
                'server_id' => $demande->id,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur: ' . $e->getMessage(),
            ];
        }
    }
}
