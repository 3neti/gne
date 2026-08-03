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
        Schema::create('roster_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->foreignId('roster_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason');
            $table->json('summary');
            $table->string('validation_status')->index();
            $table->json('validation_snapshot');
            $table->timestamps();
            $table->unique(['roster_period_id', 'revision_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_revisions');
    }
};
