<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Review;
use App\Models\Submission;
use App\Notifications\CorrectionRequestedNotification;
use App\Notifications\SubmissionApprovedNotification;
use App\Notifications\PushCompletedNotification;
use App\Services\PushDb2Service;
use Illuminate\Http\Request;

/**
 * ReviewController
 *
 * Gère toutes les actions de révision côté supérieur :
 *   - Dashboard des dossiers en attente
 *   - Consultation du détail d'un dossier
 *   - Approbation → déclenche le push DB2 immédiatement
 *   - Rejet avec commentaires → notifie l'employé
 *
 * Accessible uniquement aux utilisateurs ayant le rôle "superieur".
 * Les admins ont aussi accès à la lecture des dossiers.
 */
class ReviewController extends Controller
{
    /**
     * On injecte PushDb2Service via le constructeur.
     * Laravel résout automatiquement les dépendances (injection de dépendances).
     */
    public function __construct(private PushDb2Service $pushService)
    {
    }

    /**
     * Dashboard supérieur.
     * Affiche les dossiers groupés par statut pour une vision rapide.
     */
    public function dashboard()
    {
        // Dossiers urgents : ceux qui attendent une décision
        $enAttente = Submission::with('user')
            ->whereIn('statut', ['EN_ATTENTE', 'EN_REVISION'])
            ->orderBy('created_at')  // Les plus anciens d'abord
            ->paginate(10, ['*'], 'attente_page');

        // Dossiers traités récemment (pour historique)
        $traites = Submission::with('user')
            ->whereIn('statut', ['TERMINE', 'TERMINE_AVEC_ERREURS', 'EN_CORRECTION'])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        // Statistiques rapides pour le dashboard
        $stats = [
            'en_attente'           => Submission::whereIn('statut', ['EN_ATTENTE', 'EN_REVISION'])->count(),
            'traites_aujourd_hui'  => Submission::whereIn('statut', ['TERMINE', 'APPROUVE'])
                                        ->whereDate('updated_at', today())
                                        ->count(),
        ];

        return view('superior.dashboard', compact('enAttente', 'traites', 'stats'));
    }

    /**
     * Affiche le détail d'un dossier pour révision.
     * Marque automatiquement le dossier EN_REVISION si c'était EN_ATTENTE.
     */
    public function show(Submission $submission)
    {
        // Transition automatique vers EN_REVISION dès qu'un supérieur ouvre le dossier
        if ($submission->statut === 'EN_ATTENTE') {
            $submission->update(['statut' => 'EN_REVISION']);
        }

        // Corrections de la version courante, paginées
        $corrections = $submission->corrections()
            ->where('version', $submission->version)
            ->orderBy('ligne_ref')
            ->paginate(25);

        // Historique complet des révisions
        $reviews = $submission->reviews()->with('reviewer')->orderByDesc('created_at')->get();

        // Lecture du fichier Excel pour afficher les données brutes
        $excelData = [];
        $excelColumns = [];
        
        try {
            if (file_exists($submission->file_path)) {
                $rows = (new \Rap2hpoutre\FastExcel\FastExcel)->import($submission->file_path);
                
                // Convertir l'itérateur en array
                $excelData = $rows->toArray();
                
                // Récupérer les en-têtes (clés du premier enregistrement)
                if (! empty($excelData)) {
                    $firstRow = $excelData[0];
                    
                    // FastExcel retourne des objets stdClass, donc on accède aux propriétés
                    if (is_object($firstRow)) {
                        $excelColumns = array_keys((array) $firstRow);
                    } elseif (is_array($firstRow)) {
                        $excelColumns = array_keys($firstRow);
                    }
                }
            }
        } catch (\Exception $e) {
            // Si la lecture échoue, on affiche juste les corrections
            $excelData = [];
            $excelColumns = [];
        }

        return view('superior.submissions.show', compact('submission', 'corrections', 'reviews', 'excelData', 'excelColumns'));
    }

    /**
     * Approuve un dossier et déclenche immédiatement le push vers DB2.
     *
     * Flux :
     * 1. Vérification que le dossier est révisable
     * 2. Création de l'entrée Review (decision = APPROUVE)
     * 3. Mise à jour du statut → APPROUVE
     * 4. Push synchrone vers DB2 via PushDb2Service
     * 5. Notifications (employé + supérieur)
     * 6. Audit logs
     */
    public function approve(Request $request, Submission $submission)
    {
        // Vérification : le dossier doit être en révision ou en attente
        if (! in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION'])) {
            return back()->withErrors([
                'error' => 'Ce dossier ne peut plus être approuvé dans son état actuel.',
            ]);
        }

        // Commentaire facultatif à l'approbation
        $request->validate([
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        // 2. Enregistrement de la décision de révision
        Review::create([
            'submission_id' => $submission->id,
            'reviewer_id'   => auth()->id(),
            'commentaire'   => $request->commentaire,
            'decision'      => 'APPROUVE',
            'created_at'    => now(),
        ]);

        // 3. Mise à jour du statut du dossier
        $submission->update(['statut' => 'APPROUVE']);

        // 4. Audit de l'approbation
        AuditLog::record('APPROBATION', 'OK', [
            'submission_id' => $submission->id,
        ]);

        // Notification à l'employé : dossier approuvé, push en cours
        $submission->user->notify(new SubmissionApprovedNotification($submission));

        // 5. Push synchrone vers DB2
        $rapport = $this->pushService->push($submission);

        // 6. Notification de fin de push (à l'employé ET au supérieur)
        $submission->user->notify(new PushCompletedNotification($submission, $rapport));
        auth()->user()->notify(new PushCompletedNotification($submission, $rapport));

        // Message de retour selon le résultat du push
        $message = $rapport['erreurs'] === 0
            ? "Dossier approuvé et {$rapport['ok']} correction(s) appliquée(s) sur DB2 avec succès."
            : "Dossier approuvé. {$rapport['ok']} correction(s) OK, {$rapport['erreurs']} erreur(s). Consultez le rapport.";

        return redirect()->route('superieur.dashboard')
            ->with($rapport['erreurs'] === 0 ? 'success' : 'warning', $message);
    }

    /**
     * Rejette un dossier avec des commentaires pour l'employé.
     *
     * Le statut passe à EN_CORRECTION.
     * L'employé reçoit une notification avec les commentaires.
     */
    public function reject(Request $request, Submission $submission)
    {
        // Vérification du statut
        if (! in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION'])) {
            return back()->withErrors([
                'error' => 'Ce dossier ne peut pas être rejeté dans son état actuel.',
            ]);
        }

        // Le commentaire est OBLIGATOIRE pour un rejet
        // (l'employé doit savoir quoi corriger)
        $request->validate([
            'commentaire' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'commentaire.required' => 'Un commentaire explicatif est obligatoire pour un rejet.',
            'commentaire.min'      => 'Le commentaire doit contenir au moins 10 caractères.',
        ]);

        // Enregistrement de la décision de rejet
        Review::create([
            'submission_id' => $submission->id,
            'reviewer_id'   => auth()->id(),
            'commentaire'   => $request->commentaire,
            'decision'      => 'REJET',
            'created_at'    => now(),
        ]);

        // Mise à jour du statut → EN_CORRECTION
        $submission->update(['statut' => 'EN_CORRECTION']);

        // Audit
        AuditLog::record('REJET', 'OK', [
            'submission_id'    => $submission->id,
            'valeur_appliquee' => "Commentaire : {$request->commentaire}",
        ]);

        // Notification à l'employé avec les commentaires de correction
        $submission->user->notify(
            new CorrectionRequestedNotification($submission, $request->commentaire)
        );

        return redirect()->route('superieur.dashboard')
            ->with('success', 'Dossier rejeté. L\'employé a été notifié des corrections à apporter.');
    }

    /**
     * Liste tous les dossiers avec filtres avancés.
     * Accessible aux supérieurs et aux admins.
     */
    public function allSubmissions(Request $request)
    {
        $query = Submission::with(['user', 'latestReview.reviewer'])
            ->orderByDesc('created_at');

        // Filtre par statut
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filtre par employé
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtre par période
        if ($request->filled('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }

        $submissions = $query->paginate(15)->withQueryString();

        // Liste des employés pour le filtre
        $employes = \App\Models\User::role('employe')->orderBy('name')->get();

        return view('superior.submissions.index', compact('submissions', 'employes'));
    }
}
