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
        Schema::create('doctor_roster_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('roster_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('required_hours', 8, 2);
            $table->string('source')->default('manual')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['doctor_id', 'roster_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_roster_requirements');
    }
};
