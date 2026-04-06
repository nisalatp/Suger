<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Parent report (one per visit / panel) ──────────────────────────────
        Schema::create('lab_reports', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->date('reported_on');           // Date on the lab report
            $table->string('lab_name', 200)->nullable();   // e.g. "Nawaloka Hospitals"
            $table->string('panel_name', 200)->nullable(); // e.g. "Lipid Profile", "Full Blood Count"
            $table->string('ordered_by', 200)->nullable(); // Doctor name
            $table->longText('notes_enc')->nullable();     // Encrypted patient notes

            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->dateTime('deleted_at', 6)->nullable();

            $table->index(['user_id', 'reported_on']);
            $table->index('deleted_at');
        });

        // ── Individual test markers per report ────────────────────────────────
        Schema::create('lab_report_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lab_report_id');
            $table->foreign('lab_report_id')->references('id')->on('lab_reports')->cascadeOnDelete();

            $table->string('test_name', 200);        // e.g. "LDL Cholesterol"
            $table->string('test_key', 100)->nullable(); // slug for trend grouping, e.g. "ldl"
            $table->decimal('value', 10, 4);
            $table->string('unit', 50)->nullable();   // e.g. "mg/dL", "U/L", "%"
            $table->decimal('ref_min', 10, 4)->nullable();
            $table->decimal('ref_max', 10, 4)->nullable();
            // Computed status: low / normal / high / unknown
            $table->enum('status', ['low', 'normal', 'high', 'unknown'])->default('unknown');
            $table->string('notes', 500)->nullable();

            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();

            $table->index(['lab_report_id']);
            $table->index(['test_key']); // for trend queries across reports
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_report_items');
        Schema::dropIfExists('lab_reports');
    }
};
