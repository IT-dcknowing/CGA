<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil déclencheur Legal Flow (Q45) : avec le régime, la forme, l'effectif et la commune
     * déjà portés par le dossier, ces champs décident des obligations applicables.
     */
    public function up(): void
    {
        Schema::table('adherents', function (Blueprint $table) {
            $table->unsignedInteger('cmu_dependents')->default(0);
            $table->boolean('sells_to_consumers')->default(false);
            $table->boolean('uses_measuring_instruments')->default(false);
            $table->boolean('has_expatriates')->default(false);
            $table->boolean('has_interns')->default(false);
            $table->boolean('has_sme_status')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adherents', function (Blueprint $table) {
            $table->dropColumn([
                'cmu_dependents',
                'sells_to_consumers',
                'uses_measuring_instruments',
                'has_expatriates',
                'has_interns',
                'has_sme_status',
            ]);
        });
    }
};
