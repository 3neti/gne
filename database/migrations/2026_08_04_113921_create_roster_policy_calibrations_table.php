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
        Schema::create('roster_policy_calibrations', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('department_key')->default('anaesthesia');
            $table->string('policy_key');
            $table->unsignedInteger('revision');
            $table->string('status');
            $table->string('selected_value');
            $table->json('configuration')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('fingerprint');
            $table->timestamps();
            $table->unique(['department_key', 'policy_key', 'revision']);
            $table->index(['department_key', 'policy_key', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_policy_calibrations');
    }
};
