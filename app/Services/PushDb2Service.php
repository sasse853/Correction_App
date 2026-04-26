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
 * MISE À JOUR : utilise toutes les méthodes getXxxEffective()
 * du model StagingCorrection pour prioriser les valeurs
 * corrigées par l'employé sur toutes les colonnes.
 */
class PushDb2Service
{
    public function push(Submission $submission): array
    {
        $countOk     = 0;
        $countErreur = 0;

        $corrections = StagingCorrection::where('submission_id', $submission->id)
            ->where('version', $submission->version)
            ->where('push_statut', 'PENDING')
            ->orderBy('ligne_ref')
            ->get();

        foreach ($corrections as $correction) {
            try {
                $this->executeOnDb2($correction);

                $correction->update([
                    'appliquee'    => true,
                    'push_statut'  => 'OK',
                    'push_message' => null,
                ]);

                AuditLog::record('PUSH_DB2', 'OK', [
                    'submission_id'    => $submission->id,
                    'table_db2'        => $correction->getTableDb2Effective(),
                    'champ_modifie'    => $correction->getChampEffective(),
                    'valeur_appliquee' => $correction->getValeurEffective(),
                ]);

                $countOk++;

            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();

                $correction->update([
                    'appliquee'    => false,
                    'push_statut'  => 'ERREUR',
                    'push_message' => $errorMessage,
                ]);

                AuditLog::record('PUSH_DB2', 'ERREUR', [
                    'submission_id'    => $submission->id,
                    'table_db2'        => $correction->getTableDb2Effective(),
                    'champ_modifie'    => $correction->getChampEffective(),
                    'valeur_appliquee' => $correction->getValeurEffective(),
                    'message_erreur'   => $errorMessage,
                ]);

                Log::error("PushDb2Service: Erreur ligne {$correction->ligne_ref} - {$errorMessage}", [
                    'submission_id' => $submission->id,
                    'correction_id' => $correction->id,
                ]);

                $countErreur++;
            }
        }

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
     * Exécute la requête SQL sur DB2.
     *
     * MISE À JOUR : utilise getTableDb2Effective(), getChampEffective(),
     * getCleprimaireEffective() et getValeurEffective() pour prioriser
     * les corrections de l'employé sur toutes les colonnes.
     */
    private function executeOnDb2(StagingCorrection $correction): void
    {
        /*
         * On utilise les valeurs effectives sur TOUTES les colonnes.
         * Si l'employé a corrigé la table, le champ ou la clé primaire,
         * c'est la valeur corrigée qui est envoyée à DB2.
         */
        $tableEffective      = $correction->getTableDb2Effective();
        $champEffectif       = $correction->getChampEffective();
        $cleprimaireEffective = $correction->getCleprimaireEffective();
        $valeurEffective     = $correction->getValeurEffective();

        [$pkColonne, $pkValeur] = $this->parseCle($cleprimaireEffective);

        $schema    = config('database.connections.db2.schema');
        $tableName = $schema
            ? "{$schema}.{$tableEffective}"
            : $tableEffective;

        DB::connection('db2')->statement(
            "UPDATE {$tableName} SET {$champEffectif} = ? WHERE {$pkColonne} = ?",
            [$valeurEffective, $pkValeur]
        );
    }

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