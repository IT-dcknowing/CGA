<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Obligations d'un adhérent (Q45) : proposées par le moteur selon le profil, puis
     * confirmées ou écartées par le CGA, qui peut aussi en ajouter.
     */
    public function up(): void
    {
        Schema::create('adherent_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cga_id')->constrained();
            $table->foreignId('adherent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('obligation_id')->constrained();
            $table->string('status', 20)->default('proposed');
            $table->string('source', 20)->default('engine');
            $table->string('dismissal_reason', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['adherent_id', 'obligation_id']);
            $table->index(['cga_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adherent_obligations');
    }
};
