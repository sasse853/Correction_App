<?php

namespace App\Services;

use App\Models\StagingCorrection;
use App\Models\Submission;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * CorrectionsImport
 *
 * Parse le fichier Excel et insère les lignes dans staging_corrections.
 *
 * MODE TEST : accepte n'importe quels en-têtes.
 * Le service prend les colonnes dans l'ordre où elles apparaissent
 * dans le fichier et les mappe ainsi :
 *   - Colonne 1 → table_db2
 *   - Colonne 2 → cle_primaire
 *   - Colonne 3 → champ
 *   - Colonne 4 → valeur_correction
 *   - Colonnes supplémentaires → ignorées
 *
 * Si le fichier a moins de 4 colonnes, les colonnes manquantes
 * sont remplies avec des valeurs par défaut pour ne pas bloquer.
 *
 * En production, remplacer ce mapping par les vrais noms de colonnes.
 */
class CorrectionsImport
{
    public function __construct(private Submission $submission)
    {
    }

    /**
     * Lance l'import du fichier Excel.
     *
     * @param  mixed  $file  Chemin ou UploadedFile
     * @return int           Nombre de lignes importées
     */
    public function import($file): int
    {
        $count = 0;

        (new FastExcel)->import($file, function (array $row) use (&$count) {

            /*
             * On récupère les valeurs du tableau dans l'ordre
             * sans se soucier des noms des clés (en-têtes).
             * array_values() supprime les clés et reindexe à partir de 0.
             *
             * Exemple :
             *   ['ID' => 1, 'Date' => '...', 'Agent' => '...', 'Action' => '...']
             *   devient [1, '...', '...', '...']
             *
             * On peut ainsi prendre position 0, 1, 2, 3 quelle que soit
             * la dénomination des colonnes.
             */
            $valeurs = array_values($row);

            /*
             * Ignore les lignes complètement vides.
             * array_filter supprime les valeurs null/vides,
             * si le résultat est vide c'est une ligne blanche.
             */
            if (empty(array_filter($valeurs, fn($v) => !is_null($v) && $v !== ''))) {
                return null;
            }

            /*
             * Mapping positionnel :
             *   Position 0 → table_db2       (obligatoire)
             *   Position 1 → cle_primaire     (facultatif)
             *   Position 2 → champ            (obligatoire)
             *   Position 3 → valeur_correction (obligatoire)
             *
             * On utilise ?? pour fournir des valeurs par défaut
             * si la colonne n'existe pas dans le fichier.
             */
            $table_db2         = isset($valeurs[0]) ? (string) $valeurs[0] : 'INCONNU';
            $cle_primaire      = isset($valeurs[1]) ? (string) $valeurs[1] : null;
            $champ             = isset($valeurs[2]) ? (string) $valeurs[2] : 'CHAMP_' . ($count + 1);
            $valeur_correction = isset($valeurs[3]) ? (string) $valeurs[3] : '';

            StagingCorrection::create([
                'submission_id'     => $this->submission->id,
                'version'           => $this->submission->version,
                'ligne_ref'         => $count + 2, // +2 car ligne 1 = en-têtes
                'table_db2'         => strtoupper(trim($table_db2)),
                'champ'             => strtoupper(trim($champ)),
                'valeur_correction' => trim($valeur_correction),
                'cle_primaire'      => $cle_primaire ? trim($cle_primaire) : null,
                'appliquee'         => false,
                'push_statut'       => 'PENDING',
                'statut_revision'   => 'PENDING',
                'created_at'        => now(),
            ]);

            $count++;

            return null;
        });

        return $count;
    }
}