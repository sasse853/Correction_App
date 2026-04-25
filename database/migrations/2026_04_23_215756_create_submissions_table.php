<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('uuid', 36)->unique();
            $table->string('file_path', 255);
            $table->string('file_original_name', 255);
            $table->unsignedInteger('version')->default(1);
            $table->enum('statut', [
                'EN_ATTENTE',
                'EN_REVISION',
                'EN_CORRECTION',
                'APPROUVE',
                'TERMINE',
                'TERMINE_AVEC_ERREURS',
            ])->default('EN_ATTENTE');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'statut']);
            $table->index('statut');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
