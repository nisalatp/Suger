<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->dateTime('eaten_at_utc', 6);
            $table->string('eaten_tz', 64)->nullable();
            $table->enum('meal_type', ['breakfast', 'lunch', 'dinner', 'snack', 'other']);
            $table->decimal('total_carbs_g', 7, 2)->nullable();
            $table->decimal('total_calories_kcal', 8, 2)->nullable();
            $table->longText('notes_enc')->nullable();

            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            $table->index(['user_id', 'eaten_at_utc']);
            $table->index(['user_id', 'meal_type', 'eaten_at_utc']);
        });

        Schema::create('meal_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meal_id');
            $table->foreign('meal_id')->references('id')->on('meals')->cascadeOnDelete();

            $table->string('food_name', 255);
            $table->decimal('quantity', 10, 3)->nullable();
            $table->string('quantity_unit', 32)->nullable();
            $table->decimal('carbs_g', 7, 2)->nullable();
            $table->decimal('calories_kcal', 8, 2)->nullable();
            $table->json('meta_json')->nullable();

            $table->index('meal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_items');
        Schema::dropIfExists('meals');
    }
};
