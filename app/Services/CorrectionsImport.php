<?php

namespace App\Services;

use App\Models\StagingCorrection;
use App\Models\Submission;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * CorrectionsImport
 *
 * CORRECTION : Synchronisation avec l'affichage PhpSpreadsheet.
 *
 * On utilise exactement la même logique que dans
 * SubmissionController@show et ReviewController@show pour lire
 * le fichier — détection automatique de la ligne d'en-têtes
 * et exclusion des lignes vides.
 *
 * Ainsi staging_corrections contient exactement les mêmes
 * lignes que ce qui est affiché dans les vues, avec les mêmes
 * numéros de ligne (ligne_ref), garantissant la synchronisation
 * entre l'affichage et les données en base.
 */
class CorrectionsImport
{
    public function __construct(private Submission $submission)
    {
    }

    public function import($file): int
    {
        $count = 0;

        $chemin = is_string($file) ? $file : $file->getRealPath();

        $spreadsheet  = IOFactory::load($chemin);
        $feuille      = $spreadsheet->getActiveSheet();
        $toutesLignes = $feuille->toArray(null, true, true, false);

        /*
         * Détection automatique de la ligne d'en-têtes —
         * identique à SubmissionController et ReviewController.
         * C'est la première ligne avec au moins 3 colonnes remplies.
         */
        $indexEntetes = null;
        foreach ($toutesLignes as $i => $ligne) {
            $colonnesRemplies = count(array_filter(
                $ligne,
                fn($v) => !is_null($v) && trim((string)$v) !== ''
            ));
            if ($colonnesRemplies >= 3) {
                $indexEntetes = $i;
                break;
            }
        }

        if ($indexEntetes === null) {
            return 0;
        }

        /*
         * On récupère les en-têtes pour avoir le nombre de colonnes.
         */
        $rawEntetes = $toutesLignes[$indexEntetes];
        $nbColonnes = count(array_filter(
            $rawEntetes,
            fn($v) => !is_null($v) && trim((string)$v) !== ''
        ));

        /*
         * On parcourt les lignes de données à partir de la ligne
         * qui suit les en-têtes.
         *
         * ligne_ref = numéro de ligne dans le fichier Excel.
         * On commence à indexEntetes + 2 car :
         *   - indexEntetes     = index 0-based de la ligne d'en-têtes
         *   - indexEntetes + 1 = première ligne de données (0-based)
         *   - indexEntetes + 2 = numéro Excel réel (1-based + 1 pour en-têtes)
         */
        $lignesDonnees = array_slice($toutesLignes, $indexEntetes + 1);

        foreach ($lignesDonnees as $i => $ligne) {
            $valeurs = array_values($ligne);

            /*
             * On ignore les lignes complètement vides.
             * C'est exactement la même condition que dans les vues
             * d'affichage — garantit la synchronisation parfaite
             * entre staging_corrections et ce qui est affiché.
             */
            if (empty(array_filter($valeurs, fn($v) => !is_null($v) && trim((string)$v) !== ''))) {
                continue;
            }

            /*
             * ligne_ref = numéro de ligne réel dans Excel.
             * indexEntetes + 2 = première ligne de données en Excel
             * (indexEntetes est 0-based, +1 pour 1-based, +1 pour sauter les en-têtes)
             */
            $ligneRef = $indexEntetes + 2 + $i;

            StagingCorrection::create([
                'submission_id'     => $this->submission->id,
                'version'           => $this->submission->version,
                'ligne_ref'         => $ligneRef,
                'table_db2'         => strtoupper(trim((string)($valeurs[0] ?? 'INCONNU'))),
                'champ'             => strtoupper(trim((string)($valeurs[2] ?? 'CHAMP_' . $count))),
                'valeur_correction' => trim((string)($valeurs[3] ?? '')),
                'cle_primaire'      => isset($valeurs[1]) && trim((string)$valeurs[1]) !== ''
                                        ? trim((string)$valeurs[1])
                                        : null,
                'appliquee'         => false,
                'push_statut'       => 'PENDING',
                'statut_revision'   => 'PENDING',
                'created_at'        => now(),
            ]);

            $count++;
        }

        return $count;
    }
}