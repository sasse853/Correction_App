<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

/**
 * ═══════════════════════════════════════════════════════════════════
 * FICHIER DE ROUTES — routes/web.php
 * ═══════════════════════════════════════════════════════════════════
 *
 * Organisation des routes :
 *
 * 1. Routes publiques       → accessibles sans connexion (login)
 * 2. Routes authentifiées   → requièrent auth + compte actif
 *    ├── Dashboard général  → redirige selon le rôle
 *    ├── Routes Admin       → rôle "admin" uniquement
 *    ├── Routes Employé     → rôle "employe" uniquement
 *    ├── Routes Supérieur   → rôle "superieur" uniquement
 *    └── Routes Audit logs  → permission "view-audit-logs"
 *
 * Middleware utilisés :
 *   - "auth"              → vérifie que l'utilisateur est connecté (Laravel natif)
 *   - "check.active"      → vérifie que le compte n'est pas désactivé (custom)
 *   - "role:xxx"          → vérifie le rôle via Spatie Permission
 *   - "permission:xxx"    → vérifie une permission via Spatie Permission
 * ═══════════════════════════════════════════════════════════════════
 */

// ──────────────────────────────────────────────────────────────────
// 1. ROUTES PUBLIQUES — Pas besoin d'être connecté
// ──────────────────────────────────────────────────────────────────

// Formulaire de connexion
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');

// Traitement de la connexion
Route::post('/login', [AuthController::class, 'login'])->name('login.post');


// ──────────────────────────────────────────────────────────────────
// 2. ROUTES AUTHENTIFIÉES — Connexion + compte actif requis
// ──────────────────────────────────────────────────────────────────

Route::middleware(['auth', 'check.active'])->group(function () {

    // Déconnexion
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Redirection intelligente vers le bon dashboard selon le rôle
    Route::get('/dashboard', [AuthController::class, 'dashboard'])->name('dashboard');

    // ── 2a. ROUTES ADMIN ────────────────────────────────────────
    // Accessible uniquement au rôle "admin"
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {

        // Tableau de bord administrateur
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

        // Gestion des utilisateurs
       Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',             [AdminController::class, 'users'])->name('index');
            // SUPPRIME la ligne dupliquée ->name('')
            Route::get('/create',       [AdminController::class, 'createUser'])->name('create');
            Route::post('/',            [AdminController::class, 'storeUser'])->name('store');
            Route::get('/{user}/edit',  [AdminController::class, 'editUser'])->name('edit');
            Route::put('/{user}',       [AdminController::class, 'updateUser'])->name('update');
            Route::patch('/{user}/toggle', [AdminController::class, 'toggleUserStatus'])->name('toggle');
        });
    });

    

    // ── 2b. ROUTES EMPLOYÉ ───────────────────────────────────────
    // Accessible uniquement au rôle "employe"
    Route::middleware('role:employe')->prefix('employe')->name('employe.')->group(function () {

        // Tableau de bord employé
        Route::get('/dashboard', [SubmissionController::class, 'index'])->name('dashboard');

        // Gestion des dossiers (submissions)
        Route::prefix('submissions')->name('submissions.')->group(function () {
            // Liste de ses propres dossiers
            Route::get('/',              [SubmissionController::class, 'index'])->name('index');
            // Formulaire d'upload d'un nouveau fichier
            Route::get('/create',        [SubmissionController::class, 'create'])->name('create');
            // Traitement de l'upload
            Route::post('/',             [SubmissionController::class, 'store'])->name('store');
            // Détail d'un dossier
            Route::get('/{submission}',  [SubmissionController::class, 'show'])->name('show');
            // Re-soumission d'un fichier corrigé
            Route::post('/{submission}/resubmit', [SubmissionController::class, 'resubmit'])->name('resubmit');
        });
    });

    // ── 2c. ROUTES SUPÉRIEUR ─────────────────────────────────────
    // Accessible uniquement au rôle "superieur"
    Route::middleware('role:superieur')->prefix('superieur')->name('superieur.')->group(function () {

        // Tableau de bord supérieur (dossiers en attente)
        Route::get('/dashboard', [ReviewController::class, 'dashboard'])->name('dashboard');

        // Liste de tous les dossiers avec filtres
        Route::get('/submissions', [ReviewController::class, 'allSubmissions'])->name('submissions.index');

        // Actions sur un dossier
        Route::prefix('submissions')->name('submissions.')->group(function () {
            // Consulter le détail d'un dossier (passe en EN_REVISION auto)
            Route::get('/{submission}',          [ReviewController::class, 'show'])->name('show');
            // Approuver → déclenche le push DB2 immédiatement
            Route::post('/{submission}/approve', [ReviewController::class, 'approve'])->name('approve');
            // Rejeter avec commentaires
            Route::post('/{submission}/reject',  [ReviewController::class, 'reject'])->name('reject');
        });
        Route::get('/submissions/{submission}/download', [ReviewController::class, 'download'])->name('submissions.download');
    });

    // ── 2d. ROUTES AUDIT LOGS ────────────────────────────────────
    // Accessible aux rôles ayant la permission "view-audit-logs"
    // (supérieurs et administrateurs selon notre seeder)
    Route::middleware('permission:view-audit-logs')->prefix('audit')->name('audit.')->group(function () {

        // Tableau de bord des logs avec filtres
        Route::get('/',              [AuditLogController::class, 'index'])->name('index');
        // Export en Excel
        Route::get('/export/excel',  [AuditLogController::class, 'exportExcel'])->name('export.excel');
        // Export en PDF
        Route::get('/export/pdf',    [AuditLogController::class, 'exportPdf'])->name('export.pdf');
    });

    // ── 2e. ROUTES ADMIN — Accès aux logs ────────────────────────
    // L'admin a aussi accès aux soumissions de tous les dossiers
    // pour consultation (lecture seule)
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/submissions', [ReviewController::class, 'allSubmissions'])->name('admin.submissions.index');
        Route::get('/admin/submissions/{submission}', [ReviewController::class, 'show'])->name('admin.submissions.show');
    });
});

// ──────────────────────────────────────────────────────────────────
// Route de fallback : redirige toute URL inconnue vers le dashboard
// ──────────────────────────────────────────────────────────────────
Route::fallback(function () {
    return redirect()->route('dashboard');
});
