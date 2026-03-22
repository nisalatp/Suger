<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->longText('full_name_enc');
            $table->longText('clinic_name_enc')->nullable();
            $table->longText('address_enc')->nullable();
            $table->longText('phone_enc')->nullable();
            $table->longText('email_enc')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->longText('notes_enc')->nullable();

            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            $table->index('user_id');
            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
