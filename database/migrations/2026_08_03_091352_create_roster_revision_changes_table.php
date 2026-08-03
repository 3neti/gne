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
        Schema::create('roster_revision_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_revision_id')->constrained()->cascadeOnDelete();
            $table->string('change_type')->index();
            $table->string('entity_identifier');
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roster_revision_changes');
    }
};
