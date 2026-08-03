<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roster_generation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->foreignId('roster_period_id')->constrained()->cascadeOnDelete();
            $table->string('generator_name');
            $table->string('generator_version');
            $table->string('policy_fingerprint');
            $table->string('input_fingerprint');
            $table->string('result_fingerprint');
            $table->string('status')->index();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('summary');
            $table->string('validation_status');
            $table->timestamps();
            $table->index(['roster_period_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_generation_runs');
    }
};
