<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiche complète du registre (Q17). Le CNCGA crée une fiche minimale sans abréviation :
     * c'est le CGA qui la choisit en complétant sa fiche.
     */
    public function up(): void
    {
        Schema::table('cgas', function (Blueprint $table) {
            $table->string('abbreviation', 10)->nullable()->change();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('accountant_name')->nullable();
            $table->foreignId('tax_region_id')->nullable()->constrained();
            $table->foreignId('tax_center_id')->nullable()->constrained();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations. Échoue si un CGA n'a pas encore d'abréviation.
     */
    public function down(): void
    {
        Schema::table('cgas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_region_id');
            $table->dropConstrainedForeignId('tax_center_id');
            $table->dropColumn([
                'city',
                'address',
                'phone',
                'email',
                'manager_name',
                'accountant_name',
                'suspended_at',
                'suspension_reason',
            ]);
            $table->string('abbreviation', 10)->nullable(false)->change();
        });
    }
};
