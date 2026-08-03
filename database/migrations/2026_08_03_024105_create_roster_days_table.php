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
        Schema::create('roster_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_period_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->string('day_type')->index();
            $table->unsignedInteger('required_doctor_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['roster_period_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_days');
    }
};
