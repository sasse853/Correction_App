<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : ajout des colonnes corrigées pour toutes les colonnes
 * modifiables d'une ligne de staging_corrections.
 *
 * Contexte :
 * Quand le supérieur refuse une ligne, l'employé doit pouvoir
 * modifier TOUTES les colonnes de cette ligne sur la plateforme,
 * pas uniquement la valeur.
 *
 * Colonnes ajoutées :
 *   table_db2_corrigee    → nouvelle table DB2 si l'employé la change
 *   cle_primaire_corrigee → nouvelle clé primaire si l'employé la change
 *   champ_corrige         → nouveau nom de champ si l'employé le change
 *
 * Note : valeur_corrigee existe déjà depuis la migration précédente.
 * On n'y touche pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_corrections', function (Blueprint $table) {

            // Nouvelle table DB2 saisie par l'employé après correction
            $table->string('table_db2_corrigee', 100)
                  ->nullable()
                  ->after('valeur_corrigee');

            // Nouvelle clé primaire saisie par l'employé après correction
            $table->string('cle_primaire_corrigee', 100)
                  ->nullable()
                  ->after('table_db2_corrigee');

            // Nouveau nom de champ saisi par l'employé après correction
            $table->string('champ_corrige', 100)
                  ->nullable()
                  ->after('cle_primaire_corrigee');
        });
    }

    public function down(): void
    {
        Schema::table('staging_corrections', function (Blueprint $table) {
            $table->dropColumn([
                'table_db2_corrigee',
                'cle_primaire_corrigee',
                'champ_corrige',
            ]);
        });
    }
};