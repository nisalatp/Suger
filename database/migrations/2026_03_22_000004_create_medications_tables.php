<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('name', 255);
            $table->enum('route', ['oral', 'injection', 'pump', 'other', 'unknown'])->default('unknown');
            $table->decimal('dose_value', 10, 3)->nullable();
            $table->string('dose_unit', 32)->nullable();
            $table->boolean('is_insulin')->default(false);
            $table->json('schedule_json')->nullable();
            $table->boolean('active')->default(true);

            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            $table->index('user_id');
        });

        Schema::create('medication_events', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('medication_id');
            $table->foreign('medication_id')->references('id')->on('medications')->cascadeOnDelete();

            $table->dateTime('taken_at_utc', 6);
            $table->decimal('dose_taken_value', 10, 3)->nullable();
            $table->string('dose_taken_unit', 32)->nullable();
            $table->longText('notes_enc')->nullable();

            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['user_id', 'taken_at_utc']);
            $table->index(['medication_id', 'taken_at_utc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_events');
        Schema::dropIfExists('medications');
    }
};
