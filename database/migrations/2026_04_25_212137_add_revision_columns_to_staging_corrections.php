<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : ajout des colonnes de révision ligne par ligne
 * dans la table staging_corrections.
 *
 * Colonnes ajoutées :
 *
 *   statut_revision  → état de la ligne après révision du supérieur
 *                      PENDING  = pas encore révisée (défaut)
 *                      VALIDE   = approuvée par le supérieur
 *                      REFUSE   = refusée, l'employé doit corriger
 *
 *   commentaire_sup  → commentaire du supérieur en cas de refus
 *                      Ex: "La valeur doit être 'Dupont', vérifiez l'orthographe"
 *                      Null si la ligne est validée ou pas encore révisée.
 *
 *   valeur_corrigee  → nouvelle valeur saisie par l'employé sur la plateforme
 *                      après un refus du supérieur.
 *                      Null tant que l'employé n'a pas corrigé.
 *                      PushDb2Service utilisera cette valeur en priorité
 *                      sur valeur_correction si elle est renseignée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_corrections', function (Blueprint $table) {

            /*
             * statut_revision : on utilise un enum pour contraindre
             * les valeurs possibles directement au niveau de la BDD.
             * Valeur par défaut PENDING = pas encore révisée.
             */
            $table->enum('statut_revision', ['PENDING', 'VALIDE', 'REFUSE'])
                  ->default('PENDING')
                  ->after('push_message');

            /*
             * commentaire_sup : text pour permettre des commentaires
             * détaillés. Nullable car absent si la ligne est validée.
             */
            $table->text('commentaire_sup')
                  ->nullable()
                  ->after('statut_revision');

            /*
             * valeur_corrigee : même type que valeur_correction.
             * Nullable car renseigné uniquement après correction employé.
             */
            $table->string('valeur_corrigee')
                  ->nullable()
                  ->after('commentaire_sup');
        });
    }

    /**
     * Rollback : supprime les 3 colonnes ajoutées.
     */
    public function down(): void
    {
        Schema::table('staging_corrections', function (Blueprint $table) {
            $table->dropColumn(['statut_revision', 'commentaire_sup', 'valeur_corrigee']);
        });
    }
};