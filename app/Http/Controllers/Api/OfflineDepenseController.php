<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Depense;
use App\Models\Perte;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OfflineDepenseController extends Controller
{
    /**
     * Process an offline depense operation.
     */
    public function processDepense(User $user, array $operation): array
    {
        $data = $operation['data'];
        $clientUuid = $operation['client_uuid'];

        // Check for duplicate (idempotence)
        $existing = Depense::where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return [
                'status' => 'synced',
                'message' => 'Dépense déjà synchronisée',
                'server_id' => $existing->id,
            ];
        }

        try {
            $depense = null;
            DB::transaction(function () use ($data, $clientUuid, $user, &$depense) {
                $photoPath = null;

                // Handle base64 photo if present
                if (!empty($data['photo_base64']) && str_starts_with($data['photo_base64'], 'data:image/')) {
                    $photoPath = $this->storeBase64Photo($data['photo_base64']);
                }

                $depense = Depense::create([
                    'boutique_id' => $user->boutique_id,
                    'user_id' => $user->id,
                    'intitule' => $data['intitule'] ?? 'Dépense offline',
                    'description' => $data['description'] ?? null,
                    'montant' => $data['montant'] ?? 0,
                    'photo_justificatif' => $photoPath,
                    'statut' => 'pending',
                    'client_uuid' => $clientUuid,
                    'synced_at' => now(),
                    'is_offline' => true,
                ]);
            });

            return [
                'status' => 'synced',
                'message' => 'Dépense synchronisée avec succès',
                'server_id' => $depense->id,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Process an offline perte operation.
     */
    public function processPerte(User $user, array $operation): array
    {
        $data = $operation['data'];
        $clientUuid = $operation['client_uuid'];

        // Check for duplicate (idempotence)
        $existing = Perte::where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return [
                'status' => 'synced',
                'message' => 'Perte déjà synchronisée',
                'server_id' => $existing->id,
            ];
        }

        try {
            $perte = null;
            DB::transaction(function () use ($data, $clientUuid, $user, &$perte) {
                $photoPath = null;

                if (!empty($data['photo_base64']) && str_starts_with($data['photo_base64'], 'data:image/')) {
                    $photoPath = $this->storeBase64Photo($data['photo_base64']);
                }

                $perte = Perte::create([
                    'boutique_id' => $user->boutique_id,
                    'produit_id' => $data['produit_id'],
                    'user_id' => $user->id,
                    'quantite' => $data['quantite'] ?? 1,
                    'raison' => $data['raison'] ?? 'Perte déclarée offline',
                    'statut' => 'pending',
                    'photo_justificatif' => $photoPath,
                    'client_uuid' => $clientUuid,
                    'synced_at' => now(),
                    'is_offline' => true,
                ]);
            });

            return [
                'status' => 'synced',
                'message' => 'Perte synchronisée avec succès',
                'server_id' => $perte->id,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Store a base64-encoded photo to storage.
     */
    private function storeBase64Photo(string $photoData): ?string
    {
        if (empty($photoData) || !str_starts_with($photoData, 'data:image/')) {
            return null;
        }

        [$meta, $data] = explode(',', $photoData, 2);
        $extension = 'jpg';
        if (str_contains($meta, 'image/png')) {
            $extension = 'png';
        } elseif (str_contains($meta, 'image/webp')) {
            $extension = 'webp';
        }

        $contents = base64_decode($data);
        $filename = 'justificatifs/offline_' . uniqid() . '.' . $extension;
        Storage::disk('public')->put($filename, $contents);

        return $filename;
    }
}
