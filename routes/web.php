<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────────────────
// 1. ROUTES PUBLIQUES
// ──────────────────────────────────────────────────────────────────

Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

// ──────────────────────────────────────────────────────────────────
// 2. ROUTES AUTHENTIFIÉES
// ──────────────────────────────────────────────────────────────────

Route::middleware(['auth', 'check.active'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');

    // ── 2a. ROUTES ADMIN ────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {

        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

        // Gestion des utilisateurs
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',                [AdminController::class, 'users'])->name('index');
            Route::get('/create',          [AdminController::class, 'createUser'])->name('create');
            Route::post('/',               [AdminController::class, 'storeUser'])->name('store');
            Route::get('/{user}/edit',     [AdminController::class, 'editUser'])->name('edit');
            Route::put('/{user}',          [AdminController::class, 'updateUser'])->name('update');
            Route::patch('/{user}/toggle', [AdminController::class, 'toggleUserStatus'])->name('toggle');
        });

        // Consultation des dossiers (lecture seule pour l'admin)
        Route::get('/submissions',              [ReviewController::class, 'allSubmissions'])->name('submissions.index');
        Route::get('/submissions/{submission}', [ReviewController::class, 'show'])->name('submissions.show');
    });

    // ── 2b. ROUTES EMPLOYÉ ───────────────────────────────────────
    Route::middleware('role:employe')->prefix('employe')->name('employe.')->group(function () {

        Route::get('/dashboard', [SubmissionController::class, 'index'])->name('dashboard');

        Route::prefix('submissions')->name('submissions.')->group(function () {
            Route::get('/',                       [SubmissionController::class, 'index'])->name('index');
            Route::get('/create',                 [SubmissionController::class, 'create'])->name('create');
            Route::post('/',                      [SubmissionController::class, 'store'])->name('store');
            Route::get('/{submission}',           [SubmissionController::class, 'show'])->name('show');

            /*
             * Nouvelle route : suppression d'un dossier.
             * Disponible uniquement si statut = EN_ATTENTE.
             * Utilise DELETE comme méthode HTTP sémantique.
             */
            Route::delete('/{submission}',        [SubmissionController::class, 'destroy'])->name('destroy');

            /*
             * Nouvelle route : re-soumission globale (si on veut
             * re-uploader un fichier entier — conservée pour compatibilité).
             */
            Route::post('/{submission}/resubmit', [SubmissionController::class, 'resubmit'])->name('resubmit');

            /*
             * Nouvelle route : correction d'une ligne individuelle
             * par l'employé depuis la plateforme.
             * PATCH car on modifie partiellement une ressource existante.
             */
            Route::patch('/{submission}/corrections/{correction}', [SubmissionController::class, 'correctLine'])->name('corrections.correct');
        });
    });

    // ── 2c. ROUTES SUPÉRIEUR ─────────────────────────────────────
    Route::middleware('role:superieur')->prefix('superieur')->name('superieur.')->group(function () {

        Route::get('/dashboard', [ReviewController::class, 'dashboard'])->name('dashboard');

        Route::get('/submissions',  [ReviewController::class, 'allSubmissions'])->name('submissions.index');

        Route::prefix('submissions')->name('submissions.')->group(function () {
            Route::get('/{submission}',          [ReviewController::class, 'show'])->name('show');

            // Approbation globale (toutes les lignes déjà validées)
            Route::post('/{submission}/approve', [ReviewController::class, 'approve'])->name('approve');

            // Rejet global avec commentaire général
            Route::post('/{submission}/reject',  [ReviewController::class, 'reject'])->name('reject');

            /*
             * Nouvelles routes : validation / refus d'une ligne individuelle.
             * PATCH car modification partielle d'une correction existante.
             */
            Route::patch('/{submission}/corrections/{correction}/validate',
                [ReviewController::class, 'validateLine'])->name('corrections.validate');

            Route::patch('/{submission}/corrections/{correction}/refuse',
                [ReviewController::class, 'refuseLine'])->name('corrections.refuse');
        });
    });

    // ── 2d. ROUTES AUDIT LOGS ────────────────────────────────────
    /*
     * MODIFICATION : restreint à l'admin UNIQUEMENT.
     * Avant : middleware('permission:view-audit-logs') → supérieurs inclus.
     * Après : middleware('role:admin') → admin seulement.
     */
    Route::middleware('role:admin')->prefix('audit')->name('audit.')->group(function () {
        Route::get('/',             [AuditLogController::class, 'index'])->name('index');
        Route::get('/export/excel', [AuditLogController::class, 'exportExcel'])->name('export.excel');
        Route::get('/export/pdf',   [AuditLogController::class, 'exportPdf'])->name('export.pdf');
    });

});

// ──────────────────────────────────────────────────────────────────
// Fallback
// ──────────────────────────────────────────────────────────────────
Route::fallback(function () {
    return redirect()->route('dashboard');
});