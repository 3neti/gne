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
        Schema::create('roster_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('roster_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('roster_day_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->string('source')->default('manual')->index();
            $table->string('duty_code')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('credited_hours', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['doctor_id', 'roster_period_id', 'roster_day_id'], 'roster_assignments_primary_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_assignments');
    }
};
