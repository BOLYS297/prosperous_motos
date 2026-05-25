<?php

namespace Tests\Feature;

use App\Models\Boutique;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\Recharge;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RechargeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_recharge_achat_and_magasinier_can_confirm_it(): void
    {
        $admin = User::create([
            'nom_utilisateur' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
            'device_token' => null,
        ]);

        $magasin = Boutique::create([
            'nom' => 'Magasin Central',
            'type' => 'magasin',
            'solde' => 1000,
        ]);

        $magasinier = User::create([
            'nom_utilisateur' => 'magasinier',
            'email' => 'magasinier@example.com',
            'password' => bcrypt('password'),
            'role' => 'magasinier',
            'boutique_id' => $magasin->id,
            'device_token' => 'magasinier-token',
        ]);

        $fournisseur = Fournisseur::create([
            'nom' => 'Fournisseur Local',
            'contact' => 'contact@test.com',
        ]);

        $produit = Produit::create([
            'nom' => 'Produit Recharge',
            'prix_achat' => 75,
            'prix_vente' => 100,
            'image' => null,
        ]);

        $response = $this->actingAs($admin)
            ->withoutMiddleware([
                \App\Http\Middleware\CheckShiftTime::class,
                \App\Http\Middleware\CheckDevice::class,
                \App\Http\Middleware\LogUserActivity::class,
            ])
            ->post(route('admin.achats.store'), [
                'fournisseur_id' => $fournisseur->id,
                'boutique_id' => $magasin->id,
                'statut' => 'paye',
                'is_recharge' => true,
                'lignes' => [
                    [
                        'produit_id' => $produit->id,
                        'quantite' => 5,
                        'prix_unitaire' => 75,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.achats.index'));

        $recharge = Recharge::first();
        $this->assertNotNull($recharge);
        $this->assertSame($magasin->id, $recharge->destination_id);
        $this->assertSame('en_attente', $recharge->statut);
        $this->assertCount(1, $recharge->lignes);
        $this->assertSame(5, $recharge->lignes->first()->quantite_envoyee);

        $stockBefore = Stock::where('boutique_id', $magasin->id)
            ->where('produit_id', $produit->id)
            ->value('quantite');
        $this->assertSame(5, intval($stockBefore));

        $response = $this->actingAs($magasinier)
            ->withoutMiddleware([
                \App\Http\Middleware\CheckShiftTime::class,
                \App\Http\Middleware\CheckDevice::class,
                \App\Http\Middleware\LogUserActivity::class,
            ])
            ->post(route('magasinier.recharges.confirmer', $recharge), [
                'lignes' => [
                    [
                        'id' => $recharge->lignes->first()->id,
                        'quantite_recue' => 5,
                    ],
                ],
            ]);

        $response->assertRedirect(route('magasinier.recharges.index'));

        $recharge->refresh();
        $this->assertSame('confirmee_par_magasinier', $recharge->statut);
        $this->assertSame(5, $recharge->lignes->first()->quantite_recue);

        $this->assertSame(10, intval(Stock::where('boutique_id', $magasin->id)
            ->where('produit_id', $produit->id)
            ->value('quantite')));
    }
}
