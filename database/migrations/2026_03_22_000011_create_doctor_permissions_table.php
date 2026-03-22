<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('doctor_id')->unique(); // one permission set per doctor
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('doctor_id')->references('id')->on('doctors')->cascadeOnDelete();

            // Which health modules this doctor can see
            $table->json('modules_json')->default('{"glucose":true,"meals":false,"medications":false,"exercise":false}');

            // Whether to share raw records or daily summaries
            $table->enum('format', ['records', 'summary'])->default('summary');

            // Period of data shared
            $table->enum('period_type', ['all', 'last_30', 'last_90', 'last_year', 'custom'])->default('last_90');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();

            // Master on/off switch
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'doctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_permissions');
    }
};
