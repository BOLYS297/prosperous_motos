<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'nom_utilisateur' => ['required'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // L'utilisateur est connecté, les middlewares CheckDevice et CheckShiftTime feront les vérifications
            // sur les routes protégées. Mais on peut aussi faire une première vérification ici si on veut.
            
            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'nom_utilisateur' => 'Les identifiants fournis ne correspondent pas à nos enregistrements.',
        ])->onlyInput('nom_utilisateur');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
