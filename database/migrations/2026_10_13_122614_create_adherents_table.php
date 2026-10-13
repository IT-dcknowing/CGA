<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dossier d'un adhérent (Q21 à Q29). Les champs restent facultatifs tant que le dossier est
     * un brouillon ; la validation vérifie ceux que Q22 rend obligatoires.
     */
    public function up(): void
    {
        Schema::create('adherents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cga_id')->constrained();
            $table->string('status', 20)->default('draft');
            // Numéro attribué à la validation (2026-DCK-0143) ou numéro historique repris tel quel (Q26).
            $table->string('member_no', 40)->nullable();
            $table->unsignedInteger('member_seq')->nullable();
            $table->string('legal_form', 30)->nullable();
            $table->string('company_name');
            $table->string('trade_name')->nullable();
            $table->string('ncc', 20)->nullable()->index();
            $table->string('rccm', 50)->nullable();
            $table->string('idu', 50)->nullable();
            $table->foreignId('regime_id')->nullable()->constrained();
            $table->foreignId('sector_id')->nullable()->constrained();
            $table->string('activity')->nullable();
            $table->foreignId('tax_center_id')->nullable()->constrained();
            $table->string('address')->nullable();
            $table->string('commune', 100)->nullable();
            $table->string('plot_reference', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_title', 100)->nullable();
            $table->unsignedBigInteger('turnover')->nullable();
            $table->string('turnover_basis', 20)->nullable();
            $table->unsignedInteger('employees')->nullable();
            // Dirigeant d'une entreprise individuelle (Q29).
            $table->string('owner_last_name')->nullable();
            $table->string('owner_first_names')->nullable();
            $table->string('owner_nationality', 100)->nullable();
            $table->string('owner_id_document', 100)->nullable();
            $table->unsignedBigInteger('membership_fee')->nullable();
            $table->date('joined_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cga_id', 'member_no']);
            $table->index(['cga_id', 'status']);
        });

        // Un seul dossier ouvert par NCC dans un CGA (Q25) ; un ancien dossier radié peut coexister (Q37).
        DB::statement("CREATE UNIQUE INDEX adherents_open_ncc_unique ON adherents (cga_id, ncc) WHERE ncc IS NOT NULL AND status <> 'struck_off'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adherents');
    }
};
