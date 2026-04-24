<?php

namespace App\Services;

use App\Models\StagingCorrection;
use App\Models\Submission;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Collection;

/**
 * Import des corrections depuis un fichier Excel.
 *
 * Colonnes attendues dans le fichier :
 *   - table_db2     : nom de la table DB2 cible  (ex: CLIENT)
 *   - cle_primaire  : identifiant de la ligne    (ex: ID=1042)
 *   - champ         : nom de la colonne à modifier (ex: NOM)
 *   - valeur        : nouvelle valeur à appliquer
 */
class CorrectionsImport implements ToCollection, WithHeadingRow, WithValidation
{
    private Submission $submission;
    private array $errors = [];

    public function __construct(Submission $submission)
    {
        $this->submission = $submission;
    }

    public function collection(Collection $rows): void
    {
        $ligneRef = 1;

        foreach ($rows as $row) {
            StagingCorrection::create([
                'submission_id'    => $this->submission->id,
                'version'          => $this->submission->version,
                'ligne_ref'        => $ligneRef,
                'table_db2'        => strtoupper(trim($row['table_db2'] ?? '')),
                'champ'            => strtoupper(trim($row['champ'] ?? '')),
                'valeur_correction'=> $row['valeur'] ?? '',
                'cle_primaire'     => trim($row['cle_primaire'] ?? ''),
                'appliquee'        => false,
                'push_statut'      => 'PENDING',
                'created_at'       => now(),
            ]);
            $ligneRef++;
        }
    }

    public function rules(): array
    {
        return [
            '*.table_db2'    => 'required|string|max:100',
            '*.cle_primaire' => 'required|string|max:100',
            '*.champ'        => 'required|string|max:100',
            '*.valeur'       => 'required',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            '*.table_db2.required'    => 'La colonne "table_db2" est requise (ligne :line).',
            '*.cle_primaire.required' => 'La colonne "cle_primaire" est requise (ligne :line).',
            '*.champ.required'        => 'La colonne "champ" est requise (ligne :line).',
            '*.valeur.required'       => 'La colonne "valeur" est requise (ligne :line).',
        ];
    }
}
