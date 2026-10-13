<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique daté et motivé des régimes d'un adhérent (Q30) : il alimente les états de
     * surclassement du rapport trimestriel.
     */
    public function up(): void
    {
        Schema::create('adherent_regimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cga_id')->constrained();
            $table->foreignId('adherent_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('regime_id')->constrained();
            $table->date('effective_date');
            $table->string('reason', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adherent_regimes');
    }
};
