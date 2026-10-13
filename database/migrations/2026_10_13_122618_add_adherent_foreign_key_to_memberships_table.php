<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un accès adhérent est désormais rattaché à son dossier (annoncé à l'étape 1).
     */
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->foreign('adherent_id')->references('id')->on('adherents')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropForeign(['adherent_id']);
        });
    }
};
