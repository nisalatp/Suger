<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_patient_links', function (Blueprint $table) {
            $table->id();
            // The doctor (as a system user with role=doctor)
            $table->unsignedBigInteger('doctor_user_id');
            // The patient they are linked to
            $table->unsignedBigInteger('patient_user_id');
            // The specific Doctor record the patient created (for permission lookup)
            $table->unsignedBigInteger('linked_doctor_id')->nullable();

            $table->foreign('doctor_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('patient_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('linked_doctor_id')->references('id')->on('doctors')->nullOnDelete();

            $table->unique(['doctor_user_id', 'patient_user_id']);
            $table->index('doctor_user_id');
            $table->index('patient_user_id');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_patient_links');
    }
};
