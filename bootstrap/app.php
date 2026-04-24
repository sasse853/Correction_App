<?php

use App\Http\Middleware\CheckUserActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Point d'entrée de la configuration Laravel 11.
 *
 * C'est ici qu'on enregistre :
 *   - Les middlewares personnalisés (avec leurs alias)
 *   - Les middlewares Spatie Permission (role, permission)
 *   - La gestion des exceptions (redirections sur 403, 404, etc.)
 *
 * En Laravel 11, ce fichier remplace le Kernel.php de Laravel 10.
 */
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ── Middleware global ────────────────────────────────────
        // CheckUserActive s'applique à toutes les requêtes web authentifiées
        // On l'ajoute dans le groupe "web" pour qu'il soit toujours actif
        $middleware->appendToGroup('web', CheckUserActive::class);

        // ── Alias de middleware ──────────────────────────────────
        // Ces alias permettent d'utiliser des noms courts dans les routes
        // Ex: Route::middleware('role:admin') au lieu du FQCN complet
        $middleware->alias([
            // Notre middleware custom : vérifie que le compte est actif
            'check.active' => CheckUserActive::class,

            // Middlewares Spatie Permission
            // 'role:admin'        → vérifie que l'user a le rôle "admin"
            // 'permission:upload' → vérifie que l'user a la permission "upload"
            'role'         => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'   => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {

        // Redirection personnalisée sur erreur 403 (accès refusé)
        // Au lieu d'une page d'erreur générique, on redirige vers le dashboard
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e, $request) {
            return redirect()->route('dashboard')
                ->withErrors(['error' => 'Vous n\'avez pas les droits nécessaires pour accéder à cette page.']);
        });
    })
    ->create();
