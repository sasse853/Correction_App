<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\StagingCorrection;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PushDb2Service
 *
 * Service responsable de l'écriture des corrections validées
 * directement dans la base de données DB2 via la connexion ODBC.
 *
 * Fonctionnement :
 * - Récupère toutes les lignes de staging_corrections pour
 *   la version courante du dossier (statut PENDING)
 * - Pour chaque ligne, exécute un UPDATE ou INSERT sur DB2
 * - En cas de succès : marque la ligne comme appliquée (push_statut = OK)
 * - En cas d'erreur : journalise l'erreur sans arrêter le traitement
 *   des lignes suivantes (pas de rollback global)
 * - Met à jour le statut du dossier (TERMINE ou TERMINE_AVEC_ERREURS)
 * - Enregistre chaque opération dans audit_logs (MySQL)
 *
 * IMPORTANT : La connexion DB2 est uniquement en écriture.
 *             Aucune lecture n'est effectuée depuis DB2.
 */
class PushDb2Service
{
    /**
     * Lance le push de toutes les corrections d'un dossier vers DB2.
     *
     * @param  Submission  $submission  Le dossier approuvé à traiter
     * @return array  Rapport de résultat ['ok' => int, 'erreurs' => int]
     */
    public function push(Submission $submission): array
    {
        // Compteurs pour le rapport final
        $countOk     = 0;
        $countErreur = 0;

        // Récupération de toutes les lignes en attente de push
        // pour la version courante du dossier
        $corrections = StagingCorrection::where('submission_id', $submission->id)
            ->where('version', $submission->version)
            ->where('push_statut', 'PENDING')
            ->orderBy('ligne_ref')
            ->get();

        foreach ($corrections as $correction) {
            try {
                // Construction et exécution de la requête SQL sur DB2
                $this->executeOnDb2($correction);

                // Marquage de la ligne comme appliquée avec succès
                $correction->update([
                    'appliquee'    => true,
                    'push_statut'  => 'OK',
                    'push_message' => null,
                ]);

                // Audit log : succès ligne par ligne
                AuditLog::record('PUSH_DB2', 'OK', [
                    'submission_id'    => $submission->id,
                    'table_db2'        => $correction->table_db2,
                    'champ_modifie'    => $correction->champ,
                    'valeur_appliquee' => $correction->valeur_correction,
                ]);

                $countOk++;

            } catch (\Throwable $e) {
                // En cas d'erreur, on journalise sans arrêter le traitement
                $errorMessage = $e->getMessage();

                // Mise à jour de la ligne avec le détail de l'erreur
                $correction->update([
                    'appliquee'    => false,
                    'push_statut'  => 'ERREUR',
                    'push_message' => $errorMessage,
                ]);

                // Audit log : erreur avec détail
                AuditLog::record('PUSH_DB2', 'ERREUR', [
                    'submission_id'    => $submission->id,
                    'table_db2'        => $correction->table_db2,
                    'champ_modifie'    => $correction->champ,
                    'valeur_appliquee' => $correction->valeur_correction,
                    'message_erreur'   => $errorMessage,
                ]);

                // Log applicatif pour le débogage serveur
                Log::error("PushDb2Service: Erreur ligne {$correction->ligne_ref} - {$errorMessage}", [
                    'submission_id' => $submission->id,
                    'correction_id' => $correction->id,
                ]);

                $countErreur++;
            }
        }

        // Mise à jour du statut final du dossier
        $nouveauStatut = $countErreur === 0 ? 'TERMINE' : 'TERMINE_AVEC_ERREURS';
        $submission->update(['statut' => $nouveauStatut]);

        return [
            'ok'      => $countOk,
            'erreurs' => $countErreur,
            'total'   => $countOk + $countErreur,
            'statut'  => $nouveauStatut,
        ];
    }

    /**
     * Exécute la requête SQL sur DB2 pour une correction donnée.
     *
     * La requête est un UPDATE ciblé grâce à la cle_primaire.
     * Format de cle_primaire attendu : "COLONNE_ID=valeur" (ex: "ID=1042")
     *
     * Exemple de requête générée :
     *   UPDATE SCHEMA.CLIENT SET NOM = 'Dupont' WHERE ID = '1042'
     *
     * @param  StagingCorrection  $correction  La ligne à appliquer
     * @throws \Exception  Si la requête DB2 échoue
     */
    private function executeOnDb2(StagingCorrection $correction): void
    {
        // Parsing de la clé primaire (format: "COL=valeur")
        [$pkColonne, $pkValeur] = $this->parseCle($correction->cle_primaire);

        // Construction du nom complet de la table (avec schéma si défini)
        $schema    = config('database.connections.db2.schema');
        $tableName = $schema
            ? "{$schema}.{$correction->table_db2}"
            : $correction->table_db2;

        // Exécution via la connexion DB2 dédiée
        // On utilise des bindings pour éviter les injections SQL
        DB::connection('db2')->statement(
            "UPDATE {$tableName} SET {$correction->champ} = ? WHERE {$pkColonne} = ?",
            [
                $correction->valeur_correction,
                $pkValeur,
            ]
        );
    }

    /**
     * Décompose la clé primaire au format "COLONNE=valeur".
     *
     * Exemple : "ID=1042" → ['ID', '1042']
     *
     * @param  string  $cle  La clé primaire au format "COL=val"
     * @return array         [colonne, valeur]
     * @throws \InvalidArgumentException  Si le format est invalide
     */
    private function parseCle(string $cle): array
    {
        $parts = explode('=', $cle, 2);

        if (count($parts) !== 2 || empty(trim($parts[0])) || empty(trim($parts[1]))) {
            throw new \InvalidArgumentException(
                "Format de clé primaire invalide : '{$cle}'. Attendu : 'COLONNE=valeur'."
            );
        }

        return [trim($parts[0]), trim($parts[1])];
    }
}
