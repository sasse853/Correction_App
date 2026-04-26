<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Review;
use App\Models\Submission;
use App\Models\StagingCorrection;
use App\Notifications\CorrectionRequestedNotification;
use App\Notifications\SubmissionApprovedNotification;
use App\Notifications\PushCompletedNotification;
use App\Services\PushDb2Service;
use Illuminate\Http\Request;

/**
 * ReviewController
 *
 * Gère toutes les actions de révision côté supérieur.
 *
 * NOUVELLES MÉTHODES ajoutées :
 *   - validateLine() → valide une ligne individuelle
 *   - refuseLine()   → refuse une ligne avec commentaire
 *
 * Le workflow est maintenant :
 *   1. Le supérieur valide/refuse chaque ligne individuellement
 *   2. Quand toutes les lignes sont traitées, il clique "Finaliser"
 *      → approve() si tout est validé
 *      → reject()  si au moins une ligne est refusée
 */
class ReviewController extends Controller
{
    public function __construct(private PushDb2Service $pushService)
    {
    }

    /**
     * Dashboard supérieur.
     */
    public function dashboard()
    {
        $enAttente = Submission::with('user')
            ->whereIn('statut', ['EN_ATTENTE', 'EN_REVISION'])
            ->orderBy('created_at')
            ->paginate(10, ['*'], 'attente_page');

        $traites = Submission::with('user')
            ->whereIn('statut', ['TERMINE', 'TERMINE_AVEC_ERREURS', 'EN_CORRECTION'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $stats = [
            'en_attente'          => Submission::whereIn('statut', ['EN_ATTENTE', 'EN_REVISION'])->count(),
            'traites_aujourd_hui' => Submission::whereIn('statut', ['TERMINE', 'APPROUVE'])
                                        ->whereDate('updated_at', today())
                                        ->count(),
        ];

        return view('superior.dashboard', compact('enAttente', 'traites', 'stats'));
    }

    /**
     * Affiche le détail d'un dossier pour révision.
     * Marque automatiquement EN_REVISION si c'était EN_ATTENTE.
     */
    public function show(Submission $submission)
    {
        if ($submission->statut === 'EN_ATTENTE') {
            $submission->update(['statut' => 'EN_REVISION']);
        }

        $corrections = $submission->corrections()
            ->where('version', $submission->version)
            ->orderBy('ligne_ref')
            ->paginate(25);

        $reviews = $submission->reviews()->with('reviewer')->orderByDesc('created_at')->get();

        /*
         * Compteurs pour l'interface de révision :
         * le supérieur sait combien de lignes il lui reste à traiter.
         */
        $totalLines    = $submission->corrections()->where('version', $submission->version)->count();
        $validatedLines = $submission->corrections()->where('version', $submission->version)->where('statut_revision', 'VALIDE')->count();
        $refusedLines  = $submission->corrections()->where('version', $submission->version)->where('statut_revision', 'REFUSE')->count();
        $pendingLines  = $totalLines - $validatedLines - $refusedLines;

        return view('superior.submissions.show', compact(
            'submission', 'corrections', 'reviews',
            'totalLines', 'validatedLines', 'refusedLines', 'pendingLines'
        ));
    }

    /**
     * Valide une ligne individuelle de correction.
     *
     * PATCH /superieur/submissions/{submission}/corrections/{correction}/validate
     *
     * Met à jour statut_revision = 'VALIDE' sur la ligne.
     * Retourne une réponse JSON pour que le JS puisse
     * mettre à jour l'interface sans recharger la page.
     */
    public function validateLine(Request $request, Submission $submission, StagingCorrection $correction)
    {
        /*
         * Vérification que la correction appartient bien à cette soumission
         * et à la version courante — sécurité contre la manipulation d'URL.
         */
        if (
            $correction->submission_id !== $submission->id ||
            $correction->version !== $submission->version
        ) {
            return response()->json(['error' => 'Correction invalide.'], 403);
        }

        $correction->update(['statut_revision' => 'VALIDE', 'commentaire_sup' => null]);

        // Audit de la validation de la ligne
        AuditLog::record('APPROBATION', 'OK', [
            'submission_id'    => $submission->id,
            'table_db2'        => $correction->table_db2,
            'champ_modifie'    => $correction->champ,
            'valeur_appliquee' => "Ligne #{$correction->ligne_ref} validée",
        ]);

        return response()->json([
            'success' => true,
            'message' => "Ligne #{$correction->ligne_ref} validée.",
        ]);
    }

    /**
     * Refuse une ligne individuelle avec un commentaire obligatoire.
     *
     * PATCH /superieur/submissions/{submission}/corrections/{correction}/refuse
     *
     * Met à jour :
     *   statut_revision = 'REFUSE'
     *   commentaire_sup = commentaire saisi par le supérieur
     *
     * Ce commentaire sera visible par l'employé quand il
     * ouvrira son dossier retourné.
     */
    public function refuseLine(Request $request, Submission $submission, StagingCorrection $correction)
    {
        if (
            $correction->submission_id !== $submission->id ||
            $correction->version !== $submission->version
        ) {
            return response()->json(['error' => 'Correction invalide.'], 403);
        }

        // Le commentaire est obligatoire pour un refus de ligne
        $request->validate([
            'commentaire' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $correction->update([
            'statut_revision' => 'REFUSE',
            'commentaire_sup' => $request->commentaire,
            // On remet valeur_corrigee à null si la ligne était déjà
            // corrigée par l'employé mais refusée à nouveau
            'valeur_corrigee' => null,
        ]);

        AuditLog::record('REJET', 'OK', [
            'submission_id'    => $submission->id,
            'table_db2'        => $correction->table_db2,
            'champ_modifie'    => $correction->champ,
            'valeur_appliquee' => "Ligne #{$correction->ligne_ref} refusée : {$request->commentaire}",
        ]);

        return response()->json([
            'success'     => true,
            'message'     => "Ligne #{$correction->ligne_ref} refusée.",
            'commentaire' => $request->commentaire,
        ]);
    }

    /**
     * Finalise la révision et approuve le dossier.
     * Déclenche le push DB2.
     *
     * Appelé seulement quand TOUTES les lignes sont validées.
     */
    public function approve(Request $request, Submission $submission)
    {
        if (! in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION'])) {
            return back()->withErrors(['error' => 'Ce dossier ne peut plus être approuvé.']);
        }

        /*
         * Vérification de sécurité : il ne doit plus rester de lignes
         * PENDING ou REFUSE avant d'approuver.
         * Si le supérieur essaie d'approuver avec des lignes non traitées,
         * on le bloque.
         */
        $linesNotValidated = $submission->corrections()
            ->where('version', $submission->version)
            ->whereIn('statut_revision', ['PENDING', 'REFUSE'])
            ->count();

        if ($linesNotValidated > 0) {
            return back()->withErrors([
                'error' => "Impossible d'approuver : {$linesNotValidated} ligne(s) non validée(s). Traitez toutes les lignes avant de finaliser.",
            ]);
        }

        $request->validate(['commentaire' => ['nullable', 'string', 'max:1000']]);

        Review::create([
            'submission_id' => $submission->id,
            'reviewer_id'   => auth()->id(),
            'commentaire'   => $request->commentaire,
            'decision'      => 'APPROUVE',
            'created_at'    => now(),
        ]);

        $submission->update(['statut' => 'APPROUVE']);

        AuditLog::record('APPROBATION', 'OK', ['submission_id' => $submission->id]);

        $submission->user->notify(new SubmissionApprovedNotification($submission));

        // Push vers DB2 — PushDb2Service utilisera getValeurEffective()
        // qui priorise valeur_corrigee sur valeur_correction
        $rapport = $this->pushService->push($submission);

        $submission->user->notify(new PushCompletedNotification($submission, $rapport));
        auth()->user()->notify(new PushCompletedNotification($submission, $rapport));

        $message = $rapport['erreurs'] === 0
            ? "Dossier approuvé et {$rapport['ok']} correction(s) appliquée(s) sur DB2."
            : "Dossier approuvé. {$rapport['ok']} OK, {$rapport['erreurs']} erreur(s).";

        return redirect()->route('superieur.dashboard')
            ->with($rapport['erreurs'] === 0 ? 'success' : 'warning', $message);
    }

    /**
     * Rejette le dossier et le renvoie à l'employé pour corrections.
     * Appelé quand au moins une ligne est refusée.
     */
    public function reject(Request $request, Submission $submission)
    {
        if (! in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION'])) {
            return back()->withErrors(['error' => 'Ce dossier ne peut pas être rejeté.']);
        }

        $request->validate([
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ]);

        // Compte les lignes refusées pour le message
        $refusedCount = $submission->corrections()
            ->where('version', $submission->version)
            ->where('statut_revision', 'REFUSE')
            ->count();

        Review::create([
            'submission_id' => $submission->id,
            'reviewer_id'   => auth()->id(),
            'commentaire'   => $request->commentaire ?? "{$refusedCount} ligne(s) refusée(s). Consultez les commentaires sur chaque ligne.",
            'decision'      => 'REJET',
            'created_at'    => now(),
        ]);

        $submission->update(['statut' => 'EN_CORRECTION']);

        AuditLog::record('REJET', 'OK', [
            'submission_id'    => $submission->id,
            'valeur_appliquee' => "{$refusedCount} ligne(s) refusée(s)",
        ]);

        $submission->user->notify(
            new CorrectionRequestedNotification($submission, $request->commentaire ?? '')
        );

        return redirect()->route('superieur.dashboard')
            ->with('success', "Dossier retourné à l'employé. {$refusedCount} ligne(s) à corriger.");
    }

    /**
     * Liste tous les dossiers avec filtres.
     */
    public function allSubmissions(Request $request)
    {
        $query = Submission::with(['user', 'latestReview.reviewer'])
            ->orderByDesc('created_at');

        if ($request->filled('statut'))     $query->where('statut', $request->statut);
        if ($request->filled('user_id'))    $query->where('user_id', $request->user_id);
        if ($request->filled('date_debut')) $query->whereDate('created_at', '>=', $request->date_debut);
        if ($request->filled('date_fin'))   $query->whereDate('created_at', '<=', $request->date_fin);

        $submissions = $query->paginate(15)->withQueryString();
        $employes    = \App\Models\User::role('employe')->orderBy('name')->get();

        return view('superior.submissions.index', compact('submissions', 'employes'));
    }

    /**
 * Réinitialise une ligne à PENDING pour permettre de la re-traiter.
 */
    public function resetLine(Request $request, Submission $submission, StagingCorrection $correction)
    {
        if (
            $correction->submission_id !== $submission->id ||
            $correction->version !== $submission->version
        ) {
            return response()->json(['error' => 'Correction invalide.'], 403);
        }

        // On mémorise l'ancien statut pour que le JS sache
        // si c'était une validation ou un refus à annuler
        $ancienStatut = $correction->statut_revision;

        $correction->update([
            'statut_revision' => 'PENDING',
            'commentaire_sup' => null,
        ]);

        return response()->json([
            'success' => true,
            'was'     => $ancienStatut, // 'VALIDE' ou 'REFUSE'
        ]);
    }
}