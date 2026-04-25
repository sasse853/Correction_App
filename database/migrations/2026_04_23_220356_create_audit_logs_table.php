<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role', 50)->nullable();
            $table->enum('action', [
                'UPLOAD',
                'APPROBATION',
                'REJET',
                'CORRECTION',
                'PUSH_DB2',
                'LOGIN',
                'LOGOUT',
                'CREATE_USER',
                'UPDATE_USER',
                'DEACTIVATE_USER',
            ])->index();
            $table->foreignId('submission_id')->nullable()->constrained('submissions')->nullOnDelete();
            $table->string('table_db2', 100)->nullable();
            $table->string('champ_modifie', 100)->nullable();
            $table->text('valeur_appliquee')->nullable();
            $table->enum('statut', ['OK', 'ERREUR'])->default('OK')->index();
            $table->text('message_erreur')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['submission_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
