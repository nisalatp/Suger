<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->enum('actor_type', ['user', 'system']);
            $table->string('event_type', 64);
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->char('request_id', 36)->nullable();
            $table->binary('ip_hash')->nullable();
            $table->binary('user_agent_hash')->nullable();
            $table->json('meta_json')->nullable();

            $table->dateTime('created_at', 6)->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
