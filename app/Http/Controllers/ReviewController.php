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
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * ReviewController — branche collègue
 *
 * CORRECTION fast-excel :
 * Remplacement de PhpOffice\PhpSpreadsheet\IOFactory::load()
 * par rap2hpoutre/fast-excel pour la lecture du fichier Excel
 * dans la méthode show().
 *
 * La logique reste identique : on lit toutes les lignes du fichier
 * pour les passer à la vue sous forme de tableau associatif
 * ($excelData et $excelColumns).
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
     *
     * CORRECTION fast-excel :
     * On remplace IOFactory::load() par FastExcel->import()
     * pour lire le fichier Excel et construire $excelData/$excelColumns.
     *
     * FastExcel retourne directement une collection de tableaux
     * associatifs dont les clés = en-têtes de la première ligne.
     * C'est plus simple que PhpSpreadsheet qui nécessitait de
     * parser manuellement les lignes et les colonnes.
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

        // Lecture du fichier Excel pour afficher les données brutes
        $excelData    = [];
        $excelColumns = [];

        try {
            $cheminAbsolu = storage_path('app/private/' . $submission->file_path);

            if (file_exists($cheminAbsolu)) {
                /*
                 * AVANT (PhpSpreadsheet) :
                 *   $spreadsheet  = IOFactory::load($cheminAbsolu);
                 *   $feuille      = $spreadsheet->getActiveSheet();
                 *   $toutesLignes = $feuille->toArray(...);
                 *   // puis parsing manuel des en-têtes...
                 *
                 * APRÈS (FastExcel) :
                 *   FastExcel::import() lit le fichier et retourne
                 *   une Collection de tableaux associatifs.
                 *   Les clés = première ligne du fichier (en-têtes).
                 *   Beaucoup plus simple, pas besoin de chercher
                 *   manuellement la ligne d'en-têtes.
                 */
                $collection = (new FastExcel)->import($cheminAbsolu);

                if ($collection->isNotEmpty()) {
                    // Les colonnes = clés du premier élément de la collection
                    $excelColumns = array_keys($collection->first());

                    // Les données = toute la collection convertie en tableau PHP
                    $excelData = $collection->toArray();
                }
            }
        } catch (\Exception $e) {
            /*
             * En cas d'erreur de lecture (fichier corrompu, format
             * non supporté...), on initialise des tableaux vides
             * pour ne pas faire planter la vue.
             */
            $excelData    = [];
            $excelColumns = [];
        }

        return view('superior.submissions.show', compact(
            'submission', 'corrections', 'reviews',
            'excelData', 'excelColumns'
        ));
    }

    /**
     * Approuve un dossier et déclenche le push vers DB2.
     */
    public function approve(Request $request, Submission $submission)
    {
        if (! in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION'])) {
            return back()->withErrors([
                'error' => 'Ce dossier ne peut plus être approuvé dans son état actuel.',
            ]);
        }

        $request->validate([
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        Review::create([
            'submission_id' => $submission->id,
            'reviewer_id'   => auth()->id(),
            'commentaire'   => $request->commentaire,
            'decision'      => 'APPROUVE',
            'created_at'    => now(),
        ]);

        $submission->update(['statut' => 'APPROUVE']);

        AuditLog::record('APPROBATION', 'OK', [
            'submission_id' => $submission->id,
        ]);

        $submission->user->notify(new SubmissionApprovedNotification($submission));

        $rapport = $this->pushService->push($submission);

        $submission->user->notify(new PushCompletedNotification($submission, $rapport));
        auth()->user()->notify(new PushCompletedNotification($submission, $rapport));

        $message = $rapport['erreurs'] === 0
            ? "Dossier approuvé et {$rapport['ok']} correction(s) appliquée(s) sur DB2 avec succès."
            : "Dossier approuvé. {$rapport['ok']} OK, {$rapport['erreurs']} erreur(s). Consultez le rapport.";

        return redirect()->route('superieur.dashboard')
            ->with($rapport['erreurs'] === 0 ? 'success' : 'warning', $message);
    }

    /**
     * Rejette un dossier avec commentaires.
     */
    public function reject(Request $request, Submission $submission)
    {
        if (! in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION'])) {
            return back()->withErrors([
                'error' => 'Ce dossier ne peut pas être rejeté dans son état actuel.',
            ]);
        }

        $request->validate([
            'commentaire' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'commentaire.required' => 'Un commentaire explicatif est obligatoire pour un rejet.',
            'commentaire.min'      => 'Le commentaire doit contenir au moins 10 caractères.',
        ]);

        Review::create([
            'submission_id' => $submission->id,
            'reviewer_id'   => auth()->id(),
            'commentaire'   => $request->commentaire,
            'decision'      => 'REJET',
            'created_at'    => now(),
        ]);

        $submission->update(['statut' => 'EN_CORRECTION']);

        AuditLog::record('REJET', 'OK', [
            'submission_id'    => $submission->id,
            'valeur_appliquee' => "Commentaire : {$request->commentaire}",
        ]);

        $submission->user->notify(
            new CorrectionRequestedNotification($submission, $request->commentaire)
        );

        return redirect()->route('superieur.dashboard')
            ->with('success', 'Dossier rejeté. L\'employé a été notifié des corrections à apporter.');
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
}