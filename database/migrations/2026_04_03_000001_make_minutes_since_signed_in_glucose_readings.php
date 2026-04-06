<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Change minutes_since_last_meal / minutes_since_last_drink to signed integers.
     * Previously unsignedInteger, which caused "Out of range value" DB errors when
     * a user accidentally entered a future date for last_meal_at or last_drink_at,
     * producing a negative diffInMinutes result.
     */
    public function up(): void
    {
        Schema::table('glucose_readings', function (Blueprint $table) {
            $table->integer('minutes_since_last_meal')->nullable()->change();
            $table->integer('minutes_since_last_drink')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('glucose_readings', function (Blueprint $table) {
            $table->unsignedInteger('minutes_since_last_meal')->nullable()->change();
            $table->unsignedInteger('minutes_since_last_drink')->nullable()->change();
        });
    }
};
