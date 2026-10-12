<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cgas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation', 10)->unique();
            $table->string('ncc', 20)->nullable()->unique();
            $table->string('rccm', 50)->nullable();
            $table->string('legal_form', 50)->nullable();
            $table->unsignedBigInteger('capital')->nullable();
            $table->string('approval_no', 50)->nullable();
            $table->date('approval_date')->nullable();
            $table->date('approval_expires_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_subscribed')->default(false);
            $table->unsignedInteger('next_member_seq')->default(1);
            $table->string('logo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('stamp_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cgas');
    }
};
