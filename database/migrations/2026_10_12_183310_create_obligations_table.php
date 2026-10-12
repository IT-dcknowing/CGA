<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obligation_referential_id')->nullable()->constrained();
            // Obligation ajoutée par un CGA à la liste nationale (Q44) ; null pour le référentiel national.
            $table->foreignId('cga_id')->nullable()->index()->constrained();
            $table->string('code', 40);
            $table->string('administration_code', 20);
            $table->string('administration_name');
            $table->string('label');
            $table->string('legal_basis')->nullable();
            $table->string('periodicity', 2);
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->string('due_date', 5)->nullable();
            $table->string('platform')->nullable();
            $table->jsonb('applicability')->nullable();
            $table->jsonb('periodicity_rule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['cga_id', 'code']);
        });

        DB::statement('CREATE UNIQUE INDEX obligations_national_code_unique ON obligations (code) WHERE cga_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('obligations');
    }
};
