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

/**
 * SubmissionController
 *
 * NOUVELLES MÉTHODES :
 *   - destroy()     → supprime un dossier EN_ATTENTE
 *   - correctLine() → l'employé corrige une ligne refusée
 *                     directement sur la plateforme
 */
class SubmissionController extends Controller
{
    /**
     * Liste les dossiers de l'employé connecté.
     */
    public function index()
    {
        $submissions = Submission::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('employee.submissions.index', compact('submissions'));
    }

    /**
     * Formulaire d'upload.
     */
    public function create()
    {
        return view('employee.submissions.create');
    }

    /**
     * Traite l'upload et le parsing du fichier Excel.
     */
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
     */
    public function show(Submission $submission)
    {
        if ($submission->user_id !== auth()->id()) {
            abort(403, 'Accès non autorisé à ce dossier.');
        }

        $corrections = $submission->corrections()
            ->where('version', $submission->version)
            ->orderBy('ligne_ref')
            ->paginate(20);

        $reviews = $submission->reviews()->with('reviewer')->orderByDesc('created_at')->get();

        /*
         * Compteurs pour informer l'employé de l'état de la révision.
         * Utiles quand le dossier est EN_CORRECTION pour qu'il sache
         * combien de lignes il doit corriger.
         */
        $refusedCount = $submission->corrections()
            ->where('version', $submission->version)
            ->where('statut_revision', 'REFUSE')
            ->count();

        $correctedCount = $submission->corrections()
            ->where('version', $submission->version)
            ->where('statut_revision', 'REFUSE')
            ->whereNotNull('valeur_corrigee')
            ->count();

        return view('employee.submissions.show', compact(
            'submission', 'corrections', 'reviews',
            'refusedCount', 'correctedCount'
        ));
    }

    /**
     * Supprime un dossier soumis par l'employé.
     *
     * DELETE /employe/submissions/{submission}
     *
     * Conditions :
     *   - Le dossier doit appartenir à l'employé connecté
     *   - Le statut doit être EN_ATTENTE uniquement
     *     (impossible de supprimer un dossier déjà en révision)
     *
     * Supprime :
     *   - Le fichier Excel stocké sur le disque
     *   - Les corrections parsées dans staging_corrections
     *   - Le dossier dans submissions
     */
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

        // Suppression du fichier Excel physique
        Storage::disk('local')->delete($submission->file_path);

        // Les corrections liées sont supprimées automatiquement
        // grâce à la contrainte onDelete('cascade') en migration.
        // Si ce n'est pas le cas, on les supprime manuellement :
        $submission->corrections()->delete();

        $submission->delete();

        AuditLog::record('UPLOAD', 'OK', [
            'submission_id'    => null,
            'valeur_appliquee' => "Suppression du dossier #{$submission->id} : {$submission->file_original_name}",
        ]);

        return redirect()->route('employe.submissions.index')
            ->with('success', "Le dossier \"{$submission->file_original_name}\" a été supprimé.");
    }

    /**
     * L'employé corrige une ligne refusée directement sur la plateforme.
     *
     * PATCH /employe/submissions/{submission}/corrections/{correction}
     *
     * Met à jour valeur_corrigee sur la StagingCorrection.
     * La ligne reste en statut_revision = REFUSE jusqu'à ce que
     * le supérieur la revalide au prochain cycle.
     *
     * Retourne JSON pour mise à jour dynamique de l'interface.
     */
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
 
        /*
         * Validation : au moins une colonne doit être renseignée.
         * Toutes sont facultatives individuellement car l'employé
         * ne corrige que ce qui est nécessaire.
         */
        $request->validate([
            'valeur_corrigee'       => ['nullable', 'string', 'max:500'],
            'table_db2_corrigee'    => ['nullable', 'string', 'max:100'],
            'cle_primaire_corrigee' => ['nullable', 'string', 'max:100'],
            'champ_corrige'         => ['nullable', 'string', 'max:100'],
        ]);
 
        /*
         * On ne met à jour que les colonnes qui ont été renseignées.
         * Si l'employé laisse une colonne vide, on garde l'ancienne valeur.
         * array_filter(null check) évite d'écraser avec null.
         */
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
            'success'               => true,
            'valeur_corrigee'       => $correction->fresh()->valeur_corrigee,
            'table_db2_corrigee'    => $correction->fresh()->table_db2_corrigee,
            'cle_primaire_corrigee' => $correction->fresh()->cle_primaire_corrigee,
            'champ_corrige'         => $correction->fresh()->champ_corrige,
            'message'               => 'Corrections enregistrées.',
        ]);
    }

    /**
     * Re-soumission globale après que toutes les lignes
     * refusées ont été corrigées par l'employé.
     *
     * Vérifie qu'il ne reste plus de lignes REFUSE sans valeur_corrigee
     * avant de repasser le dossier en EN_ATTENTE.
     */
    public function resubmit(Request $request, Submission $submission)
    {
        if ($submission->user_id !== auth()->id()) {
            abort(403);
        }

        if ($submission->statut !== 'EN_CORRECTION') {
            return back()->withErrors(['error' => 'Ce dossier ne peut pas être re-soumis.']);
        }

        /*
         * Vérification : toutes les lignes refusées doivent avoir
         * une valeur_corrigee avant de re-soumettre.
         */
        $uncorrectedLines = $submission->corrections()
            ->where('version', $submission->version)
            ->where('statut_revision', 'REFUSE')
            ->whereNull('valeur_corrigee')
            ->count();

        if ($uncorrectedLines > 0) {
            return back()->withErrors([
                'error' => "Impossible de re-soumettre : {$uncorrectedLines} ligne(s) refusée(s) n'ont pas encore été corrigées.",
            ]);
        }

        // Repasse le dossier en EN_ATTENTE pour que le supérieur
        // puisse revoir les lignes corrigées
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