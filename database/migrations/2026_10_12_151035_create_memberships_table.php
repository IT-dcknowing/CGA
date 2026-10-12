<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('space', 20);
            $table->foreignId('cga_id')->nullable()->index()->constrained();
            // La clé étrangère vers adherents sera ajoutée avec la table, à l'étape 4.
            $table->unsignedBigInteger('adherent_id')->nullable()->index();
            $table->string('role', 30);
            $table->boolean('is_primary')->default(false);
            $table->string('status', 20)->default('invited');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
