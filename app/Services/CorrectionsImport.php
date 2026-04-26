<?php

namespace App\Services;

use App\Models\StagingCorrection;
use App\Models\Submission;
use Rap2hpoutre\FastExcel\FastExcel;

/**
 * CorrectionsImport
 *
 * Lit le fichier Excel soumis par l'employé et insère
 * chaque ligne dans la table staging_corrections.
 *
 * ADAPTATION fast-excel :
 * La version originale utilisait Maatwebsite\Excel qui n'est
 * pas compatible avec PHP 8.5. On utilise ici
 * rap2hpoutre/fast-excel qui fonctionne parfaitement.
 *
 * Différence majeure avec Maatwebsite :
 *   - Maatwebsite : classe qui implémente des interfaces,
 *     appelée via Excel::import(new CorrectionsImport(), $file)
 *   - FastExcel    : on instancie FastExcel et on appelle
 *     ->import($file, callback) directement
 *
 * Format attendu du fichier Excel (première ligne = en-têtes) :
 *   | table_db2 | cle_primaire | champ | valeur |
 */
class CorrectionsImport
{
    /**
     * On injecte la soumission pour pouvoir lier chaque
     * correction à son dossier et à sa version.
     */
    public function __construct(private Submission $submission)
    {
    }

    /**
     * Lance l'import du fichier Excel vers staging_corrections.
     *
     * FastExcel lit le fichier ligne par ligne et appelle
     * le callback pour chaque ligne. On y insère la correction
     * en base si les colonnes obligatoires sont présentes.
     *
     * @param  mixed  $file  Chemin du fichier ou UploadedFile Laravel
     * @return int           Nombre de lignes importées avec succès
     * @throws \Exception    Si le fichier est illisible ou mal formaté
     */
    public function import($file): int
    {
        $count = 0;

        /*
         * FastExcel::import() lit le fichier et appelle le callback
         * pour chaque ligne sous forme de tableau associatif.
         * Les clés du tableau = les en-têtes de la première ligne Excel.
         *
         * Exemple de $row :
         * [
         *   'table_db2'    => 'CLIENT',
         *   'cle_primaire' => 'ID=1042',
         *   'champ'        => 'NOM',
         *   'valeur'       => 'Dupont',
         * ]
         */
        (new FastExcel)->import($file, function (array $row) use (&$count) {

            /*
             * On nettoie les clés du tableau pour éviter les problèmes
             * d'espaces ou de casse dans les en-têtes Excel.
             * Ex: "Table_DB2 " devient "table_db2"
             */
            $row = array_combine(
                array_map(fn($key) => strtolower(trim($key)), array_keys($row)),
                array_values($row)
            );

            /*
             * Validation minimale : on ignore les lignes vides
             * ou celles qui n'ont pas les colonnes obligatoires.
             * On ne lève pas d'exception pour ne pas bloquer
             * l'import entier à cause d'une ligne vide.
             */
            if (
                empty($row['table_db2']) ||
                empty($row['champ'])     ||
                ! isset($row['valeur'])
            ) {
                return null; // FastExcel ignore les retours null
            }

            /*
             * Insertion en base dans staging_corrections.
             * Chaque ligne est liée à la soumission et à sa version
             * pour permettre la traçabilité multi-versions.
             *
             * push_statut = 'PENDING' par défaut car le push DB2
             * n'a pas encore eu lieu (il se fait à l'approbation).
             */
            StagingCorrection::create([
                'submission_id'      => $this->submission->id,
                'version'            => $this->submission->version,
                'ligne_ref'          => $count + 2, // +2 car ligne 1 = en-têtes
                'table_db2'          => strtoupper(trim($row['table_db2'])),
                'champ'              => strtoupper(trim($row['champ'])),
                'valeur_correction'  => trim($row['valeur']),
                'cle_primaire'       => isset($row['cle_primaire']) ? trim($row['cle_primaire']) : null,
                'appliquee'          => false,
                'push_statut'        => 'PENDING',
                'created_at'         => now(),
            ]);

            $count++;

            return null; // FastExcel n'utilise pas la valeur de retour
        });

        return $count;
    }
}