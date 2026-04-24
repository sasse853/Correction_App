<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Submission;
use App\Notifications\NewSubmissionNotification;
use App\Services\CorrectionsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use App\Models\User;

/**
 * SubmissionController
 *
 * Gère tout le cycle de vie des dossiers côté employé :
 *   - Liste de ses propres dossiers
 *   - Upload d'un nouveau fichier Excel
 *   - Consultation du détail d'un dossier
 *   - Re-soumission d'un fichier corrigé (après rejet)
 *
 * Chaque upload crée une nouvelle entrée dans "submissions"
 * et parse les lignes vers "staging_corrections".
 * Toutes les versions sont conservées pour la traçabilité.
 */
class SubmissionController extends Controller
{
    /**
     * Liste les dossiers de l'employé connecté.
     * Paginée et triée du plus récent au plus ancien.
     */
    public function index()
    {
        $submissions = Submission::where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('employee.submissions.index', compact('submissions'));
    }

    /**
     * Affiche le formulaire d'upload d'un nouveau fichier Excel.
     */
    public function create()
    {
        return view('employee.submissions.create');
    }

    /**
     * Traite l'upload et le parsing du fichier Excel.
     *
     * Étapes :
     * 1. Validation du fichier (type MIME, taille, extension)
     * 2. Stockage sécurisé du fichier original dans Storage
     * 3. Création du dossier (submission) en base
     * 4. Parsing ligne par ligne via CorrectionsImport
     * 5. Notification au(x) supérieur(s)
     * 6. Enregistrement dans audit_logs
     */
    public function store(Request $request)
    {
        // 1. Validation du fichier uploadé
        $request->validate([
            'fichier'      => [
                'required',
                'file',
                // On accepte uniquement les formats Excel
                'mimes:xlsx,xls,csv',
                // Taille maximale en KB (20 MB = 20480 KB)
                'max:20480',
            ],
            'description'  => ['nullable', 'string', 'max:500'],
        ], [
            'fichier.required' => 'Veuillez sélectionner un fichier Excel.',
            'fichier.mimes'    => 'Le fichier doit être au format .xlsx, .xls ou .csv.',
            'fichier.max'      => 'Le fichier ne doit pas dépasser 20 Mo.',
        ]);

        $file = $request->file('fichier');

        // 2. Stockage du fichier original avec un nom unique (UUID)
        //    pour éviter les conflits et garantir la traçabilité
        $uuid     = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $filePath  = $file->storeAs(
            'submissions',           // Dossier dans storage/app/
            "{$uuid}.{$extension}",  // Nom unique
            'local'                  // Disque local (pas public)
        );

        // 3. Création du dossier en base avec statut initial EN_ATTENTE
        $submission = Submission::create([
            'user_id'           => auth()->id(),
            'uuid'              => $uuid,
            'file_path'         => $filePath,
            'file_original_name'=> $file->getClientOriginalName(),
            'version'           => 1,
            'statut'            => 'EN_ATTENTE',
            'description'       => $request->description,
        ]);

        // 4. Parsing du fichier Excel vers staging_corrections
        //    On utilise notre classe d'import personnalisée
        try {
            Excel::import(new CorrectionsImport($submission), $file);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Si le fichier a des erreurs de structure, on supprime
            // le dossier créé et le fichier stocké pour rester propre
            Storage::disk('local')->delete($filePath);
            $submission->delete();

            // On retourne les erreurs de validation du fichier
            $failures = collect($e->failures())->map(fn($f) => $f->errors())->flatten();
            return back()->withErrors(['fichier' => $failures->toArray()]);
        }

        // 5. Notification à tous les supérieurs de la nouvelle soumission
        $superieurs = User::role('superieur')->where('is_active', true)->get();
        Notification::send($superieurs, new NewSubmissionNotification($submission));

        // 6. Audit log
        AuditLog::record('UPLOAD', 'OK', [
            'submission_id'    => $submission->id,
            'valeur_appliquee' => "Fichier : {$submission->file_original_name}",
        ]);

        return redirect()->route('employe.submissions.show', $submission)
            ->with('success', 'Votre fichier a été soumis avec succès. Les supérieurs ont été notifiés.');
    }

    /**
     * Affiche le détail d'un dossier.
     * Un employé ne peut voir que ses propres dossiers.
     */
    public function show(Submission $submission)
    {
        // Sécurité : un employé ne peut consulter que ses propres dossiers
        if ($submission->user_id !== auth()->id()) {
            abort(403, 'Accès non autorisé à ce dossier.');
        }

        // Chargement des corrections de la version courante
        $corrections = $submission->corrections()
            ->where('version', $submission->version)
            ->orderBy('ligne_ref')
            ->paginate(20);

        // Chargement de l'historique des révisions
        $reviews = $submission->reviews()->with('reviewer')->orderByDesc('created_at')->get();

        return view('employee.submissions.show', compact('submission', 'corrections', 'reviews'));
    }

    /**
     * Re-soumission d'un fichier corrigé.
     *
     * Disponible uniquement si le statut est EN_CORRECTION.
     * Crée une nouvelle version du dossier (v1 → v2 → v3 etc.)
     * Les anciennes versions sont conservées dans staging_corrections.
     */
    public function resubmit(Request $request, Submission $submission)
    {
        // Vérification que le dossier appartient à l'employé
        if ($submission->user_id !== auth()->id()) {
            abort(403);
        }

        // Vérification que le dossier est bien en attente de correction
        if ($submission->statut !== 'EN_CORRECTION') {
            return back()->withErrors(['error' => 'Ce dossier ne peut pas être re-soumis dans son état actuel.']);
        }

        // Validation du nouveau fichier
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $file      = $request->file('fichier');
        $newVersion = $submission->version + 1;

        // Stockage du nouveau fichier avec un nouveau nom unique
        $uuid      = (string) Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $filePath  = $file->storeAs('submissions', "{$uuid}.{$extension}", 'local');

        // Mise à jour du dossier existant (nouvelle version, nouveau fichier)
        $submission->update([
            'uuid'              => $uuid,
            'file_path'         => $filePath,
            'file_original_name'=> $file->getClientOriginalName(),
            'version'           => $newVersion,
            'statut'            => 'EN_ATTENTE',
        ]);

        // Parsing du nouveau fichier (les anciennes corrections sont conservées)
        try {
            Excel::import(new CorrectionsImport($submission), $file);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Rollback : on restaure l'ancienne version
            Storage::disk('local')->delete($filePath);
            $submission->update(['version' => $newVersion - 1, 'statut' => 'EN_CORRECTION']);

            return back()->withErrors(['fichier' => 'Le fichier soumis contient des erreurs de structure.']);
        }

        // Notification aux supérieurs
        $superieurs = User::role('superieur')->where('is_active', true)->get();
        Notification::send($superieurs, new NewSubmissionNotification($submission));

        // Audit
        AuditLog::record('CORRECTION', 'OK', [
            'submission_id'    => $submission->id,
            'valeur_appliquee' => "Re-soumission v{$newVersion} : {$submission->file_original_name}",
        ]);

        return redirect()->route('employe.submissions.show', $submission)
            ->with('success', "Votre correction (version {$newVersion}) a été soumise avec succès.");
    }
}
