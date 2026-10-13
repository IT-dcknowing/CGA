<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Faits du planning (Q46 à Q48). Le planning lui-même est calculé à la demande ; seules
     * les échéances dont quelque chose a été enregistré ont une ligne ici, repérée par la clé
     * stable (adhérent, obligation, date d'échéance).
     */
    public function up(): void
    {
        Schema::create('deadline_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cga_id')->constrained();
            $table->foreignId('adherent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('obligation_id')->constrained();
            $table->date('due_date');
            // Situation validée par le CGA, qui a le dernier mot (Q47).
            $table->string('status', 20)->default('to_do');
            $table->unsignedBigInteger('amount_due')->nullable();
            $table->unsignedBigInteger('amount_paid')->nullable();
            $table->timestamp('declared_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            // Ce que l'adhérent a signalé, en attente de confirmation par le CGA (Q47).
            $table->string('adherent_report', 20)->nullable();
            $table->unsignedBigInteger('adherent_report_amount')->nullable();
            $table->timestamp('adherent_reported_at')->nullable();
            $table->foreignId('adherent_reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['adherent_id', 'obligation_id', 'due_date']);
            $table->index(['cga_id', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deadline_records');
    }
};
