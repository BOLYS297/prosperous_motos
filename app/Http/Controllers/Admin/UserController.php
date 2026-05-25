<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = \App\Models\User::where('role', '!=', 'super_admin')->get();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $boutiques = \App\Models\Boutique::all();
        return view('admin.users.create', compact('boutiques'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom_utilisateur' => 'required|string|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|in:magasinier,boutiquier',
            'shift' => 'nullable|in:matin,soir',
            'boutique_id' => 'nullable|exists:boutiques,id'
        ]);

        \App\Models\User::create([
            'nom_utilisateur' => $request->nom_utilisateur,
            'email' => $request->email,
            'password' => bcrypt($request->password),
            'role' => $request->role,
            'shift' => $request->shift,
            'boutique_id' => $request->boutique_id
        ]);

        return redirect()->route('admin.users.index')->with('success', 'Employé ajouté avec succès.');
    }

    public function destroy(\App\Models\User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')->with('success', 'Employé supprimé.');
    }

    public function authorizeDevice(\App\Models\User $user)
    {
        $token = \Illuminate\Support\Str::random(60);
        $user->update(['device_token' => $token]);
        
        // On crée un cookie qui n'expire pratiquement jamais (5 ans)
        $cookie = cookie('device_token', $token, 2628000);
        
        return redirect()->route('admin.users.index')->with('success', 'Appareil autorisé pour cet employé.')->withCookie($cookie);
    }

    public function resetDevice(\App\Models\User $user)
    {
        $user->update(['device_token' => null]);
        return redirect()->route('admin.users.index')->with('success', 'L\'appareil de cet employé a été réinitialisé.');
    }
}
