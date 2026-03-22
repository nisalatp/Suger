<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->dateTime('performed_at_utc', 6);
            $table->string('activity_type', 128);
            $table->unsignedSmallInteger('duration_minutes');
            $table->enum('intensity', ['low', 'moderate', 'high', 'unknown'])->default('unknown');
            $table->longText('notes_enc')->nullable();

            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            $table->index(['user_id', 'performed_at_utc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
