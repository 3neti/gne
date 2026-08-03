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
        Schema::create('doctor_schedule_requests', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $table->foreignId('roster_period_id')->constrained()->cascadeOnDelete();
            $table->string('request_type')->index();
            $table->string('status')->index();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['roster_period_id', 'doctor_id', 'status'], 'doctor_requests_period_doctor_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_requests');
    }
};
