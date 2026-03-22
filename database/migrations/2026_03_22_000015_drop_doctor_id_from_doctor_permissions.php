<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_permissions', function (Blueprint $table) {
            if (Schema::hasColumn('doctor_permissions', 'doctor_id')) {
                $table->dropColumn('doctor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('doctor_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('doctor_id')->nullable()->after('doctor_user_id');
        });
    }
};
