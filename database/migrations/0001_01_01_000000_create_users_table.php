<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('google_sub', 64)->unique();
            $table->string('email', 255)->unique();
            $table->boolean('email_verified')->default(false);
            $table->string('name', 255)->nullable();
            $table->string('given_name', 255)->nullable();
            $table->string('family_name', 255)->nullable();
            $table->string('avatar_url', 2048)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('password')->nullable();
            $table->dateTime('last_login_at', 6)->nullable()->index();
            $table->rememberToken();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable()->index();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
