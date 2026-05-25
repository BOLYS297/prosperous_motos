<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achat;
use App\Models\Boutique;
use App\Models\LogActivite;
use App\Models\Vente;
use Illuminate\Support\Carbon;

class AdminController extends Controller
{
    public function index()
    {
        $entrepriseSolde = Boutique::sum('solde');
        $boutiques = Boutique::orderByDesc('solde')->take(2)->get();
        $topBoutique = $boutiques->get(0);
        $secondBoutique = $boutiques->get(1);

        $dettesFournisseurs = Achat::where('statut', 'dette')
            ->get()
            ->sum(fn(Achat $achat) => $achat->reste_a_payer);

        $weeklySales = collect();
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $day = Carbon::today()->subDays($daysAgo);
            $total = Vente::whereDate('created_at', $day)
                ->sum('montant_total');

            $weeklySales->push([
                'label' => $day->translatedFormat('D'),
                'date' => $day->format('Y-m-d'),
                'total' => $total,
            ]);
        }

        $maxWeeklySales = $weeklySales->max('total') ?: 1;

        $recentActivities = LogActivite::latest()->take(5)->get();

        $rechargesEnAttente = \App\Models\Recharge::where('statut', 'confirmee_par_magasinier')->count();

        return view('admin.dashboard', compact(
            'entrepriseSolde',
            'topBoutique',
            'secondBoutique',
            'dettesFournisseurs',
            'weeklySales',
            'maxWeeklySales',
            'recentActivities',
            'rechargesEnAttente'
        ));
    }
}
