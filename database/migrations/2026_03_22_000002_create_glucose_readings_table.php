<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glucose_readings', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->dateTime('measured_at_utc', 6);
            $table->string('measured_tz', 64)->nullable();
            $table->decimal('value_raw', 6, 2);
            $table->enum('unit', ['mg_dL', 'mmol_L']);
            $table->decimal('value_mgdl', 6, 2);

            $table->enum('time_of_day', [
                'pre_breakfast', 'post_breakfast',
                'pre_lunch', 'post_lunch',
                'pre_dinner', 'post_dinner',
                'bedtime', 'overnight', 'other'
            ])->default('other');

            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack', 'none', 'unknown'])->default('unknown');
            $table->dateTime('last_meal_at_utc', 6)->nullable();
            $table->dateTime('last_drink_at_utc', 6)->nullable();
            $table->boolean('is_fasting')->default(false);
            $table->unsignedInteger('minutes_since_last_meal')->nullable();
            $table->unsignedInteger('minutes_since_last_drink')->nullable();
            $table->boolean('insulin_taken')->default(false);
            $table->boolean('meds_taken')->default(false);
            $table->json('symptoms_json')->nullable();
            $table->longText('notes_enc')->nullable();
            $table->enum('source', ['manual', 'import', 'device', 'unknown'])->default('manual');

            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            // Composite indexes per SRS
            $table->index(['user_id', 'measured_at_utc']);
            $table->index(['user_id', 'value_mgdl']);
            $table->index(['user_id', 'is_fasting', 'measured_at_utc']);
            $table->index(['user_id', 'time_of_day', 'measured_at_utc']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glucose_readings');
    }
};
