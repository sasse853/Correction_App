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
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * ReviewController
 *
 * MISE À JOUR de show() :
 * Utilise PhpSpreadsheet pour lire le fichier Excel brut
 * et l'afficher tel quel dans la vue du supérieur,
 * avec toutes ses colonnes originales.
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
     * MISE À JOUR : Lecture du fichier Excel brut avec PhpSpreadsheet
     * pour afficher le tableau exact avec toutes ses colonnes originales.
     *
     * Variables supplémentaires passées à la vue :
     *   - $excelColumns      → array des noms de colonnes
     *   - $excelData         → array des lignes de données
     *   - $fileExists        → bool
     *   - $correctionsByLine → Collection indexée par ligne_ref
     */
    public function show(Submission $submission)
    {
        if ($submission->statut === 'EN_ATTENTE') {
            $submission->update(['statut' => 'EN_REVISION']);
        }

        $corrections = $submission->corrections()
            ->where('version', $submission->version)
            ->orderBy('ligne_ref')
            ->get();

        $reviews = $submission->reviews()->with('reviewer')->orderByDesc('created_at')->get();

        $totalLines     = $submission->corrections()->where('version', $submission->version)->count();
        $validatedLines = $submission->corrections()->where('version', $submission->version)->where('statut_revision', 'VALIDE')->count();
        $refusedLines   = $submission->corrections()->where('version', $submission->version)->where('statut_revision', 'REFUSE')->count();
        $pendingLines   = $totalLines - $validatedLines - $refusedLines;

        /*
         * Lecture du fichier Excel brut avec PhpSpreadsheet.
         * Même logique que dans SubmissionController@show :
         * détection automatique de la ligne d'en-têtes.
         */
        $excelData    = [];
        $excelColumns = [];
        $fileExists   = false;

        try {
            $cheminAbsolu = storage_path('app/private/' . $submission->file_path);

            if (! file_exists($cheminAbsolu)) {
                $cheminAbsolu = storage_path('app/' . $submission->file_path);
            }

            if (file_exists($cheminAbsolu)) {
                $fileExists   = true;
                $spreadsheet  = IOFactory::load($cheminAbsolu);
                $feuille      = $spreadsheet->getActiveSheet();
                $toutesLignes = $feuille->toArray(null, true, true, false);

                /*
                 * Détection automatique de la ligne d'en-têtes :
                 * première ligne avec au moins 3 colonnes non vides.
                 */
                $indexEntetes = null;
                foreach ($toutesLignes as $i => $ligne) {
                    $colonnesRemplies = count(array_filter(
                        $ligne,
                        fn($v) => !is_null($v) && $v !== ''
                    ));
                    if ($colonnesRemplies >= 3) {
                        $indexEntetes = $i;
                        break;
                    }
                }
                $firstDataLine = ($indexEntetes !== null) ? $indexEntetes + 2 : 2;

                if ($indexEntetes !== null) {
                    $rawEntetes   = $toutesLignes[$indexEntetes];
                    $excelColumns = array_values(array_filter(
                        $rawEntetes,
                        fn($v) => !is_null($v) && $v !== ''
                    ));
                    $nbColonnes   = count($excelColumns);

                    $lignesDonnees = array_slice($toutesLignes, $indexEntetes + 1);

                    foreach ($lignesDonnees as $ligne) {
                        $valeurs = array_values($ligne);

                        if (empty(array_filter($valeurs, fn($v) => !is_null($v) && $v !== ''))) {
                            continue;
                        }

                        $excelData[] = array_combine(
                            $excelColumns,
                            array_slice(
                                array_pad($valeurs, $nbColonnes, ''),
                                0,
                                $nbColonnes
                            )
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            $excelData    = [];
            $excelColumns = [];
        }

        // Indexation des corrections par ligne_ref pour la vue
        $correctionsByLine = $corrections->keyBy('ligne_ref');

        return view('superior.submissions.show', compact(
            'submission', 'corrections', 'reviews',
            'totalLines', 'validatedLines', 'refusedLines', 'pendingLines',
            'excelData', 'excelColumns', 'fileExists', 'correctionsByLine', 'firstDataLine'
        ));
    }

    /**
     * Valide une ligne individuelle.
     */
    public function validateLine(Request $request, Submission $submission, StagingCorrection $correction)
    {
        if (
            $correction->submission_id !== $submission->id ||
            $correction->version !== $submission->version
        ) {
            return response()->json(['error' => 'Correction invalide.'], 403);
        }

        $correction->update(['statut_revision' => 'VALIDE', 'commentaire_sup' => null]);

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
     * Refuse une ligne avec commentaire obligatoire.
     */
    public function refuseLine(Request $request, Submission $submission, StagingCorrection $correction)
    {
        if (
            $correction->submission_id !== $submission->id ||
            $correction->version !== $submission->version
        ) {
            return response()->json(['error' => 'Correction invalide.'], 403);
        }

        $request->validate([
            'commentaire' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $correction->update([
            'statut_revision' => 'REFUSE',
            'commentaire_sup' => $request->commentaire,
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
     * Réinitialise une ligne à PENDING.
     */
    public function resetLine(Request $request, Submission $submission, StagingCorrection $correction)
    {
        if (
            $correction->submission_id !== $submission->id ||
            $correction->version !== $submission->version
        ) {
            return response()->json(['error' => 'Correction invalide.'], 403);
        }

        $ancienStatut = $correction->statut_revision;

        $correction->update([
            'statut_revision' => 'PENDING',
            'commentaire_sup' => null,
        ]);

        return response()->json([
            'success' => true,
            'was'     => $ancienStatut,
        ]);
    }

    /**
     * Approuve le dossier et déclenche le push DB2.
     */
    public function approve(Request $request, Submission $submission)
    {
        if (! in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION'])) {
            return back()->withErrors(['error' => 'Ce dossier ne peut plus être approuvé.']);
        }

        $linesNotValidated = $submission->corrections()
            ->where('version', $submission->version)
            ->whereIn('statut_revision', ['PENDING', 'REFUSE'])
            ->count();

        if ($linesNotValidated > 0) {
            return back()->withErrors([
                'error' => "Impossible d'approuver : {$linesNotValidated} ligne(s) non validée(s).",
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
     * Rejette le dossier et le renvoie à l'employé.
     */
    public function reject(Request $request, Submission $submission)
    {
        if (! in_array($submission->statut, ['EN_ATTENTE', 'EN_REVISION'])) {
            return back()->withErrors(['error' => 'Ce dossier ne peut pas être rejeté.']);
        }

        $request->validate(['commentaire' => ['nullable', 'string', 'max:2000']]);

        $refusedCount = $submission->corrections()
            ->where('version', $submission->version)
            ->where('statut_revision', 'REFUSE')
            ->count();

        Review::create([
            'submission_id' => $submission->id,
            'reviewer_id'   => auth()->id(),
            'commentaire'   => $request->commentaire ?? "{$refusedCount} ligne(s) refusée(s).",
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
}