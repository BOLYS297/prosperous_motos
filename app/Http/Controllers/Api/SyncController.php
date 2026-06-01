<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Boutique;
use App\Models\Grossiste;
use App\Models\PrixGrossiste;
use App\Models\Produit;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncController extends Controller
{
    /**
     * Bootstrap sync — returns all data needed for offline mode.
     * Called on first load or when a full refresh is needed.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $boutiqueId = $user->boutique_id;

        $boutique = Boutique::find($boutiqueId);
        $produits = Produit::orderBy('nom')->get(['id', 'nom', 'prix_achat', 'prix_vente', 'image', 'updated_at']);

        $stocks = Stock::where('boutique_id', $boutiqueId)
            ->get(['produit_id', 'quantite', 'updated_at']);

        $grossistes = Grossiste::with(['prixProduits:id,grossiste_id,produit_id,prix_vente'])
            ->get(['id', 'nom', 'code', 'contact']);

        $prixGrossistes = PrixGrossiste::all(['id', 'grossiste_id', 'produit_id', 'prix_vente']);

        return response()->json([
            'success' => true,
            'data' => [
                'boutique' => $boutique,
                'produits' => $produits,
                'stocks' => $stocks,
                'grossistes' => $grossistes,
                'prix_grossistes' => $prixGrossistes,
                'user' => [
                    'id' => $user->id,
                    'nom_utilisateur' => $user->nom_utilisateur,
                    'role' => $user->role,
                    'boutique_id' => $user->boutique_id,
                ],
            ],
            'server_time' => now()->toISOString(),
        ]);
    }

    /**
     * Delta sync — returns data changed since a given timestamp.
     */
    public function delta(Request $request): JsonResponse
    {
        $request->validate([
            'since' => 'required|date',
        ]);

        $since = $request->input('since');
        $user = $request->user();
        $boutiqueId = $user->boutique_id;

        $produits = Produit::where('updated_at', '>', $since)
            ->get(['id', 'nom', 'prix_achat', 'prix_vente', 'image', 'updated_at']);

        $stocks = Stock::where('boutique_id', $boutiqueId)
            ->where('updated_at', '>', $since)
            ->get(['produit_id', 'quantite', 'updated_at']);

        $grossistes = Grossiste::where('updated_at', '>', $since)
            ->with(['prixProduits:id,grossiste_id,produit_id,prix_vente'])
            ->get(['id', 'nom', 'code', 'contact']);

        $prixGrossistes = PrixGrossiste::where('updated_at', '>', $since)
            ->get(['id', 'grossiste_id', 'produit_id', 'prix_vente']);

        $boutique = Boutique::where('id', $boutiqueId)
            ->where('updated_at', '>', $since)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'boutique' => $boutique,
                'produits' => $produits,
                'stocks' => $stocks,
                'grossistes' => $grossistes,
                'prix_grossistes' => $prixGrossistes,
            ],
            'server_time' => now()->toISOString(),
            'has_changes' => $produits->isNotEmpty() || $stocks->isNotEmpty() || $grossistes->isNotEmpty() || $prixGrossistes->isNotEmpty() || $boutique !== null,
        ]);
    }

    /**
     * Push sync — receives batch of offline operations and processes them.
     */
    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'operations' => 'required|array',
            'operations.*.client_uuid' => 'required|uuid',
            'operations.*.type' => 'required|in:vente,depense,perte,transfert',
            'operations.*.data' => 'required|array',
            'operations.*.timestamp' => 'required|date',
        ]);

        $results = [];
        $operations = $request->input('operations');

        // Sort operations by timestamp to process in chronological order
        usort($operations, fn($a, $b) => strtotime($a['timestamp']) - strtotime($b['timestamp']));

        foreach ($operations as $operation) {
            $result = match ($operation['type']) {
                'vente' => app(OfflineVenteController::class)->processVente($request->user(), $operation),
                'depense' => app(OfflineDepenseController::class)->processDepense($request->user(), $operation),
                'perte' => app(OfflineDepenseController::class)->processPerte($request->user(), $operation),
                'transfert' => app(OfflineTransfertController::class)->processTransfert($request->user(), $operation),
                default => ['status' => 'error', 'message' => 'Type d\'opération inconnu'],
            };

            $results[] = array_merge(['client_uuid' => $operation['client_uuid']], $result);
        }

        return response()->json([
            'success' => true,
            'results' => $results,
            'server_time' => now()->toISOString(),
        ]);
    }

    /**
     * Status check — connectivity ping + server timestamp.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'online' => true,
            'server_time' => now()->toISOString(),
        ]);
    }
}
