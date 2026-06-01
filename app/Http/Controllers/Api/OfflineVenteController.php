<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Boutique;
use App\Models\PrixGrossiste;
use App\Models\Produit;
use App\Models\Stock;
use App\Models\Vente;
use App\Models\VenteLigne;
use App\Models\User;
use App\Services\SyncConflictHandler;
use Illuminate\Support\Facades\DB;

class OfflineVenteController extends Controller
{
    /**
     * Process a single offline vente operation.
     * Called from SyncController::push()
     */
    public function processVente(User $user, array $operation): array
    {
        $data = $operation['data'];
        $clientUuid = $operation['client_uuid'];

        // Check for duplicate (idempotence)
        $existing = Vente::where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return [
                'status' => 'synced',
                'message' => 'Vente déjà synchronisée',
                'server_id' => $existing->id,
            ];
        }

        $boutiqueId = $user->boutique_id;
        $produitId = $data['produit_id'] ?? null;
        $quantite = intval($data['quantite'] ?? 0);
        $isGrossiste = boolval($data['is_grossiste'] ?? false);
        $grossisteId = $isGrossiste ? ($data['grossiste_id'] ?? null) : null;

        // Validate product exists
        $produit = Produit::find($produitId);
        if (!$produit) {
            return [
                'status' => 'error',
                'message' => 'Produit introuvable',
            ];
        }

        // Check stock
        $stock = Stock::where('boutique_id', $boutiqueId)
            ->where('produit_id', $produitId)
            ->first();

        $hasConflict = false;
        $conflictReason = null;

        if (!$stock || $stock->quantite < $quantite) {
            $hasConflict = true;
            $availableQty = $stock ? $stock->quantite : 0;
            $conflictReason = "Stock insuffisant: demandé {$quantite}, disponible {$availableQty}";
        }

        // Determine unit price
        $unitPrice = $produit->prix_vente;
        if ($isGrossiste && $grossisteId) {
            $prixGrossiste = PrixGrossiste::where('grossiste_id', $grossisteId)
                ->where('produit_id', $produitId)
                ->first();

            if ($prixGrossiste) {
                $unitPrice = $prixGrossiste->prix_vente;
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Aucun tarif grossiste défini pour ce produit',
                ];
            }
        }

        $total = $unitPrice * $quantite;

        try {
            $vente = null;
            DB::transaction(function () use (
                $clientUuid, $boutiqueId, $user, $produit, $produitId,
                $quantite, $unitPrice, $total, $grossisteId, $isGrossiste,
                $stock, $hasConflict, $conflictReason, $operation, &$vente
            ) {
                $vente = Vente::create([
                    'boutique_id' => $boutiqueId,
                    'user_id' => $user->id,
                    'montant_total' => $total,
                    'grossiste_id' => $grossisteId,
                    'client_uuid' => $clientUuid,
                    'synced_at' => now(),
                    'is_offline' => true,
                    'sync_conflict' => $hasConflict,
                    'sync_conflict_reason' => $conflictReason,
                ]);

                VenteLigne::create([
                    'vente_id' => $vente->id,
                    'produit_id' => $produitId,
                    'quantite' => $quantite,
                    'prix_unitaire' => $unitPrice,
                    'est_grossiste' => $isGrossiste,
                ]);

                // Decrement stock (even if conflict — admin will resolve)
                if ($stock) {
                    $stock->decrement('quantite', $quantite);
                }

                // Update boutique balance
                $boutique = Boutique::find($boutiqueId);
                if ($boutique) {
                    $boutique->increment('solde', $total);
                }
            });

            // Notify admins if conflict
            if ($hasConflict) {
                app(SyncConflictHandler::class)->notifyConflict($vente, $conflictReason);
            }

            return [
                'status' => $hasConflict ? 'conflict' : 'synced',
                'message' => $hasConflict
                    ? "Vente synchronisée avec conflit: {$conflictReason}"
                    : 'Vente synchronisée avec succès',
                'server_id' => $vente->id,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Erreur lors de la synchronisation: ' . $e->getMessage(),
            ];
        }
    }
}
