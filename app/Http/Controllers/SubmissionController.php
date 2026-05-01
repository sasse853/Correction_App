<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Submission;
use App\Models\StagingCorrection;
use App\Notifications\NewSubmissionNotification;
use App\Services\CorrectionsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;
use Rap2hpoutre\FastExcel\FastExcel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * SubmissionController
 *
 * MISE À JOUR de show() :
 * Utilise PhpSpreadsheet (v5.7, compatible PHP 8.5) pour lire
 * le fichier Excel brut et afficher son contenu exact,
 * même si le fichier a un titre sur la première ligne,
 * des cellules fusionnées, ou plusieurs lignes d'en-têtes.
 *
 * Stratégie de lecture :
 *   1. On charge le fichier avec IOFactory::load()
 *   2. On lit toutes les lignes du fichier
 *   3. On détecte automatiquement la ligne d'en-têtes :
 *      c'est la première ligne qui contient au moins 3 colonnes remplies
 *   4. On construit le tableau associatif à partir de là
 */
class SubmissionController extends Controller
{
    public function index()
    {
        $submissions = Submission::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('employee.submissions.index', compact('submissions'));
    }

    public function create()
    {
        return view('employee.submissions.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'fichier'     => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'fichier.required' => 'Veuillez sélectionner un fichier Excel.',
            'fichier.mimes'    => 'Le fichier doit être au format .xlsx, .xls ou .csv.',
            'fichier.max'      => 'Le fichier ne doit pas dépasser 20 Mo.',
        ]);

        $file      = $request->file('fichier');
        $uuid      = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $filePath  = $file->storeAs('submissions', "{$uuid}.{$extension}", 'local');

        $submission = Submission::create([
            'user_id'            => auth()->id(),
            'uuid'               => $uuid,
            'file_path'          => $filePath,
            'file_original_name' => $file->getClientOriginalName(),
            'version'            => 1,
            'statut'             => 'EN_ATTENTE',
            'description'        => $request->description,
        ]);

        try {
            (new CorrectionsImport($submission))->import($file);
        } catch (\Exception $e) {
            Storage::disk('local')->delete($filePath);
            $submission->delete();
            return back()->withErrors([
                'fichier' => 'Le fichier soumis est invalide : ' . $e->getMessage(),
            ]);
        }

        $superieurs = User::role('superieur')->where('is_active', true)->get();
        Notification::send($superieurs, new NewSubmissionNotification($submission));

        AuditLog::record('UPLOAD', 'OK', [
            'submission_id'    => $submission->id,
            'valeur_appliquee' => "Fichier : {$submission->file_original_name}",
        ]);

        return redirect()->route('employe.submissions.show', $submission)
            ->with('success', 'Votre fichier a été soumis avec succès. Les supérieurs ont été notifiés.');
    }

    /**
     * Affiche le détail d'un dossier (vue employé).
     *
     * Lit le fichier Excel avec PhpSpreadsheet pour afficher
     * le contenu exact, toutes colonnes confondues.
     */
    public function show(Submission $submission)
    {
        if ($submission->user_id !== auth()->id()) {
            abort(403, 'Accès non autorisé à ce dossier.');
        }

        $corrections = $submission->corrections()
            ->where('version', $submission->version)
            ->orderBy('ligne_ref')
            ->get();

        $reviews = $submission->reviews()->with('reviewer')->orderByDesc('created_at')->get();

        $refusedCount = $submission->corrections()
            ->where('version', $submission->version)
            ->where('statut_revision', 'REFUSE')
            ->count();

        $correctedCount = $submission->corrections()
            ->where('version', $submission->version)
            ->where('statut_revision', 'REFUSE')
            ->where(function($q) {
                $q->whereNotNull('valeur_corrigee')
                  ->orWhereNotNull('table_db2_corrigee')
                  ->orWhereNotNull('champ_corrige')
                  ->orWhereNotNull('cle_primaire_corrigee');
            })
            ->count();

        // Lecture du fichier Excel brut avec PhpSpreadsheet
        $excelData    = [];
        $excelColumns = [];
        $fileExists   = false;

        try {
            $cheminAbsolu = storage_path('app/private/' . $submission->file_path);

            // Fallback si le fichier est stocké sans /private/
            if (! file_exists($cheminAbsolu)) {
                $cheminAbsolu = storage_path('app/' . $submission->file_path);
            }

            if (file_exists($cheminAbsolu)) {
                $fileExists  = true;
                $spreadsheet = IOFactory::load($cheminAbsolu);
                $feuille     = $spreadsheet->getActiveSheet();
                $toutesLignes = $feuille->toArray(null, true, true, false);

                /*
                 * Détection automatique de la ligne d'en-têtes.
                 * On cherche la première ligne qui contient
                 * au moins 3 colonnes non vides.
                 * Cela permet de gérer les fichiers avec un titre
                 * sur la première ligne (comme ton fichier).
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
                    /*
                     * Les en-têtes = la ligne détectée.
                     * On ne garde que les colonnes non vides pour
                     * éviter les colonnes fantômes.
                     */
                    $rawEntetes   = $toutesLignes[$indexEntetes];
                    $excelColumns = array_values(array_filter(
                        $rawEntetes,
                        fn($v) => !is_null($v) && $v !== ''
                    ));
                    $nbColonnes   = count($excelColumns);

                    // Les données = toutes les lignes après les en-têtes
                    $lignesDonnees = array_slice($toutesLignes, $indexEntetes + 1);

                    foreach ($lignesDonnees as $ligne) {
                        $valeurs = array_values($ligne);

                        // Ignore les lignes complètement vides
                        if (empty(array_filter($valeurs, fn($v) => !is_null($v) && $v !== ''))) {
                            continue;
                        }

                        /*
                         * Construit un tableau associatif :
                         * clé = nom de colonne, valeur = cellule
                         * On tronque ou complète pour avoir exactement
                         * $nbColonnes valeurs.
                         */
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

        return view('employee.submissions.show', compact(
            'submission', 'corrections', 'reviews',
            'refusedCount', 'correctedCount',
            'excelData', 'excelColumns', 'fileExists',
            'correctionsByLine',
            'firstDataLine'
        ));
    }

    public function destroy(Submission $submission)
    {
        if ($submission->user_id !== auth()->id()) {
            abort(403);
        }

        if ($submission->statut !== 'EN_ATTENTE') {
            return back()->withErrors([
                'error' => 'Impossible de supprimer ce dossier : il est déjà en cours de révision.',
            ]);
        }

        Storage::disk('local')->delete($submission->file_path);
        $submission->corrections()->delete();
        $submission->delete();

        AuditLog::record('UPLOAD', 'OK', [
            'submission_id'    => null,
            'valeur_appliquee' => "Suppression du dossier #{$submission->id} : {$submission->file_original_name}",
        ]);

        return redirect()->route('employe.submissions.index')
            ->with('success', "Le dossier \"{$submission->file_original_name}\" a été supprimé.");
    }

    public function correctLine(Request $request, Submission $submission, StagingCorrection $correction)
    {
        if ($submission->user_id !== auth()->id()) {
            return response()->json(['error' => 'Accès non autorisé.'], 403);
        }

        if ($submission->statut !== 'EN_CORRECTION') {
            return response()->json(['error' => 'Ce dossier ne peut pas être modifié.'], 422);
        }

        if (
            $correction->submission_id !== $submission->id ||
            $correction->version !== $submission->version
        ) {
            return response()->json(['error' => 'Correction invalide.'], 403);
        }

        if ($correction->statut_revision !== 'REFUSE') {
            return response()->json(['error' => 'Cette ligne ne nécessite pas de correction.'], 422);
        }

        $request->validate([
            'valeur_corrigee'       => ['nullable', 'string', 'max:500'],
            'table_db2_corrigee'    => ['nullable', 'string', 'max:100'],
            'cle_primaire_corrigee' => ['nullable', 'string', 'max:100'],
            'champ_corrige'         => ['nullable', 'string', 'max:100'],
        ]);

        $updates = array_filter([
            'valeur_corrigee'       => $request->valeur_corrigee       ? trim($request->valeur_corrigee) : null,
            'table_db2_corrigee'    => $request->table_db2_corrigee    ? strtoupper(trim($request->table_db2_corrigee)) : null,
            'cle_primaire_corrigee' => $request->cle_primaire_corrigee ? trim($request->cle_primaire_corrigee) : null,
            'champ_corrige'         => $request->champ_corrige         ? strtoupper(trim($request->champ_corrige)) : null,
        ], fn($v) => !is_null($v));

        if (empty($updates)) {
            return response()->json(['error' => 'Aucune correction fournie.'], 422);
        }

        $correction->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Corrections enregistrées.',
        ]);
    }

    public function resubmit(Request $request, Submission $submission)
    {
        if ($submission->user_id !== auth()->id()) {
            abort(403);
        }

        if ($submission->statut !== 'EN_CORRECTION') {
            return back()->withErrors(['error' => 'Ce dossier ne peut pas être re-soumis.']);
        }

        $uncorrectedLines = $submission->corrections()
            ->where('version', $submission->version)
            ->where('statut_revision', 'REFUSE')
            ->where(function($q) {
                $q->whereNull('valeur_corrigee')
                  ->whereNull('table_db2_corrigee')
                  ->whereNull('champ_corrige')
                  ->whereNull('cle_primaire_corrigee');
            })
            ->count();

        if ($uncorrectedLines > 0) {
            return back()->withErrors([
                'error' => "Impossible de re-soumettre : {$uncorrectedLines} ligne(s) non corrigée(s).",
            ]);
        }

        $submission->update(['statut' => 'EN_ATTENTE']);

        $superieurs = User::role('superieur')->where('is_active', true)->get();
        Notification::send($superieurs, new NewSubmissionNotification($submission));

        AuditLog::record('CORRECTION', 'OK', [
            'submission_id'    => $submission->id,
            'valeur_appliquee' => "Re-soumission v{$submission->version} après corrections en ligne",
        ]);

        return redirect()->route('employe.submissions.show', $submission)
            ->with('success', 'Vos corrections ont été soumises. Le supérieur a été notifié.');
    }
}