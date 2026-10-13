<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Radiations (Q34, Q35) et leur éventuelle annulation sous 30 jours (Q37). Une radiation
     * annulée reste ici : elle alimente la trace et les états de résiliation du rapport.
     */
    public function up(): void
    {
        Schema::create('strike_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cga_id')->constrained();
            $table->foreignId('adherent_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('strike_off_reason_id')->constrained();
            $table->string('details', 500)->nullable();
            $table->text('observation');
            $table->date('effective_date');
            $table->string('document_path')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strike_offs');
    }
};
