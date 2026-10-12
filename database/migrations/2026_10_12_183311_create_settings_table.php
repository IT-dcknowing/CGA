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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // null : valeur du réseau, fixée par le CNCGA.
            $table->foreignId('cga_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cga_id', 'key']);
        });

        DB::statement('CREATE UNIQUE INDEX settings_network_key_unique ON settings (key) WHERE cga_id IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
