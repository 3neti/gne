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
        Schema::create('roster_assignment_explanations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_assignment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('roster_generation_run_id')->constrained()->cascadeOnDelete();
            $table->json('reason_codes');
            $table->json('facts');
            $table->unsignedInteger('ranking_position');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_assignment_explanations');
    }
};
