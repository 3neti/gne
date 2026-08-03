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
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('full_name');
            $table->string('employee_identifier')->nullable()->unique();
            $table->string('employment_type')->index();
            $table->boolean('active')->default(true)->index();
            $table->decimal('contracted_hours', 8, 2)->nullable();
            $table->string('contracted_hours_period')->nullable();
            $table->decimal('standard_daily_hours', 5, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
