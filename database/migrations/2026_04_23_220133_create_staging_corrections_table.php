<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('ligne_ref');
            $table->string('table_db2', 100)->nullable();
            $table->string('champ', 100);
            $table->text('valeur_correction');
            $table->string('cle_primaire', 100)->nullable()->comment('Identifiant de la ligne dans DB2');
            
            // On retire ->index() ici pour éviter le doublon d'index
            $table->boolean('appliquee')->default(false);
            
            $table->enum('push_statut', ['PENDING', 'OK', 'ERREUR'])->default('PENDING');
            $table->text('push_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Un seul index composite sur submission_id + version
            $table->index(['submission_id', 'version']);
            
            // Un seul index sur appliquee ici
            $table->index('appliquee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_corrections');
    }
};