<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * AuthController
 *
 * Gère toute la logique d'authentification :
 *   - Affichage du formulaire de connexion
 *   - Traitement de la tentative de connexion
 *   - Déconnexion
 *
 * Chaque connexion et déconnexion est tracée dans audit_logs
 * pour garantir la traçabilité complète des accès.
 */
class AuthController extends Controller
{
    /**
     * Affiche le formulaire de connexion.
     * Si l'utilisateur est déjà connecté, on le redirige
     * directement vers son tableau de bord.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Traite la tentative de connexion.
     *
     * Étapes :
     * 1. Validation des champs (email + mot de passe)
     * 2. Tentative d'authentification via Auth::attempt()
     * 3. Vérification que le compte est actif (double sécurité)
     * 4. Régénération du token CSRF (protection contre fixation de session)
     * 5. Enregistrement de l'action dans audit_logs
     * 6. Redirection vers le dashboard selon le rôle
     */
    public function login(Request $request)
    {
        // 1. Validation des données du formulaire
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // 2. Tentative de connexion avec "se souvenir de moi" optionnel
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Email ou mot de passe incorrect.',
                ]);
        }

        // 3. Double vérification du statut actif du compte
        //    (le middleware CheckUserActive le fait aussi, mais on est prudents ici)
        if (! Auth::user()->is_active) {
            Auth::logout();
            return back()->withErrors([
                'email' => 'Votre compte a été désactivé. Contactez l\'administrateur.',
            ]);
        }

        // 4. Régénération du token de session (bonne pratique sécurité)
        $request->session()->regenerate();

        // 5. Enregistrement de la connexion dans les audit logs
        AuditLog::record('LOGIN');

        // 6. Redirection vers le tableau de bord
        return redirect()->intended(route('dashboard'));
    }

    /**
     * Déconnecte l'utilisateur.
     *
     * On enregistre d'abord la déconnexion dans les logs
     * AVANT de vider la session (sinon Auth::user() serait null).
     */
    public function logout(Request $request)
    {
        // On logue avant de déconnecter
        AuditLog::record('LOGOUT');

        // Déconnexion propre
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Vous avez été déconnecté avec succès.');
    }

    /**
     * Redirige l'utilisateur vers le bon dashboard selon son rôle.
     * Cette méthode est appelée sur la route /dashboard.
     */
    public function dashboard()
    {
        $user = Auth::user();

        if ($user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('superieur')) {
            return redirect()->route('superieur.dashboard');
        }

        // Par défaut : rôle employé
        return redirect()->route('employe.dashboard');
    }
}
