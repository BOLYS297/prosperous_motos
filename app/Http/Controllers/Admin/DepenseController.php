<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Boutique;
use App\Models\Depense;
use App\Notifications\BoutiqueExpenseNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class DepenseController extends Controller
{
    public function create()
    {
        $boutiques = Boutique::orderBy('nom')->get();
        return view('admin.depenses.create', compact('boutiques'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'boutique_id' => 'required|exists:boutiques,id',
            'intitule' => 'required|string|max:255',
            'description' => 'nullable|string',
            'montant' => 'required|numeric|min:0',
            'photo_justificatif' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $boutique = Boutique::findOrFail($request->input('boutique_id'));
        $photoPath = null;

        if ($request->hasFile('photo_justificatif')) {
            $photoPath = $request->file('photo_justificatif')->store('justificatifs', 'public');
        }

        DB::transaction(function () use ($request, $boutique, &$photoPath) {
            Depense::create([
                'boutique_id' => $boutique->id,
                'user_id' => Auth::id(),
                'intitule' => $request->input('intitule'),
                'description' => $request->input('description'),
                'montant' => $request->input('montant'),
                'photo_justificatif' => $photoPath,
                'statut' => 'approved',
                'admin_id' => Auth::id(),
                'validated_at' => now(),
            ]);

            $boutique->decrement('solde', $request->input('montant'));
        });

        $this->notifyBoutiqueOfAdminExpense(
            $boutique,
            $request->input('intitule'),
            $request->input('montant'),
            $request->input('description')
        );

        return redirect()->route('admin.dashboard')->with('success', 'Dépense personnelle enregistrée et la boutique a été notifiée.');
    }

    protected function notifyBoutiqueOfAdminExpense(Boutique $boutique, string $intitule, float $montant, ?string $description)
    {
        $users = \App\Models\User::where('role', 'boutiquier')
            ->where('boutique_id', $boutique->id)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $reason = trim($description ?: "Dépense administrative : {$intitule}");
        $actionUrl = route('boutiquier.depenses.create', [
            'type' => 'perte',
            'raison' => $reason,
        ]);

        Notification::send($users, new BoutiqueExpenseNotification(
            'Nouvelle dépense administrative',
            "Une dépense de " . number_format($montant, 0, ',', ' ') . " FCFA a été imputée à votre boutique.",
            'Signaler une perte',
            $actionUrl
        ));
    }
}
