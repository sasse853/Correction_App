<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware : CheckUserActive
 *
 * Ce middleware s'exécute à chaque requête authentifiée.
 * Son rôle est de vérifier que le compte de l'utilisateur connecté
 * est toujours actif (is_active = true).
 *
 * Si l'administrateur a désactivé le compte entre-temps,
 * l'utilisateur est automatiquement déconnecté et redirigé
 * vers la page de login avec un message d'erreur explicite.
 *
 * Il doit être enregistré dans bootstrap/app.php (Laravel 11)
 * ou dans Kernel.php (Laravel 10 et antérieur).
 */
class CheckUserActive
{
    /**
     * @param  Request  $request  La requête HTTP entrante
     * @param  Closure  $next     Le prochain middleware dans la chaîne
     */
    public function handle(Request $request, Closure $next): Response
    {
        // On vérifie uniquement si un utilisateur est connecté
        if (Auth::check()) {

            // Si le compte est désactivé (is_active = false)
            if (! Auth::user()->is_active) {

                // On déconnecte proprement la session
                Auth::logout();

                // On invalide la session pour éviter toute réutilisation
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Redirection vers login avec message d'erreur
                return redirect()->route('login')
                    ->withErrors([
                        'email' => 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.',
                    ]);
            }
        }

        // Compte actif : on laisse passer la requête
        return $next($request);
    }
}
