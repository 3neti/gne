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
        Schema::create('doctor_schedule_request_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_schedule_request_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->timestamps();
            $table->unique(['doctor_schedule_request_id', 'date'], 'doctor_request_dates_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_schedule_request_dates');
    }
};
