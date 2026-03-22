<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->enum('consent_type', [
                'terms', 'privacy', 'health_processing',
                'genetic_processing', 'doctor_sharing',
                'research', 'marketing'
            ]);
            $table->string('version', 32);
            $table->dateTime('granted_at', 6);
            $table->dateTime('revoked_at', 6)->nullable();
            $table->enum('ui_surface', ['web', 'mobile', 'api', 'unknown'])->default('web');
            $table->json('proof_json')->nullable();

            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['user_id', 'consent_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
