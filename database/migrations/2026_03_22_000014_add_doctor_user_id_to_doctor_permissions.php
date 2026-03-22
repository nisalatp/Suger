<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_permissions', function (Blueprint $table) {
            // Replace the old doctor_id (->doctors table) with doctor_user_id (->users table)
            // Drop foreign key and old column first
            $table->dropForeign(['doctor_id']);
            $table->dropUnique(['doctor_id']);

            // Add the new user-based doctor column
            $table->unsignedBigInteger('doctor_user_id')->nullable()->after('user_id');
            $table->foreign('doctor_user_id')->references('id')->on('users')->cascadeOnDelete();

            // Rename user_id -> patient_user_id for clarity
            $table->renameColumn('user_id', 'patient_user_id');

            // Add unique constraint on the (patient, doctor) pair
            $table->unique(['patient_user_id', 'doctor_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('doctor_permissions', function (Blueprint $table) {
            $table->dropForeign(['doctor_user_id']);
            $table->dropUnique(['patient_user_id', 'doctor_user_id']);
            $table->dropColumn('doctor_user_id');
            $table->renameColumn('patient_user_id', 'user_id');

            $table->unsignedBigInteger('doctor_id')->after('user_id');
            $table->foreign('doctor_id')->references('id')->on('doctors')->cascadeOnDelete();
            $table->unique('doctor_id');
        });
    }
};
