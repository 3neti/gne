<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gne_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('repository_identifier')->unique();
            $table->string('title');
            $table->string('source_path');
            $table->json('metadata');
            $table->timestamps();
        });

        Schema::create('gne_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->string('repository_identifier')->unique();
            $table->string('profile_identifier')->index();
            $table->string('title');
            $table->string('source_path');
            $table->json('metadata');
            $table->timestamps();
        });

        Schema::create('gne_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->string('repository_identifier');
            $table->string('revision');
            $table->string('artifact_type')->index();
            $table->string('profile_identifier')->index();
            $table->string('scenario_identifier')->index();
            $table->string('status')->nullable();
            $table->string('source_path');
            $table->json('metadata');
            $table->timestamps();
            $table->unique(['repository_identifier', 'revision']);
        });

        Schema::create('gne_artifact_relationships', function (Blueprint $table): void {
            $table->id();
            $table->string('source_identifier');
            $table->string('source_revision');
            $table->string('relationship_type');
            $table->string('target_identifier');
            $table->string('target_revision')->nullable();
            $table->string('source_path');
            $table->timestamps();
            $table->index(['source_identifier', 'source_revision']);
            $table->index(['target_identifier', 'target_revision']);
        });

        Schema::create('gne_materialization_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('repository_fingerprint', 64)->index();
            $table->string('status')->index();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->json('counts')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gne_materialization_runs');
        Schema::dropIfExists('gne_artifact_relationships');
        Schema::dropIfExists('gne_artifacts');
        Schema::dropIfExists('gne_scenarios');
        Schema::dropIfExists('gne_profiles');
    }
};
