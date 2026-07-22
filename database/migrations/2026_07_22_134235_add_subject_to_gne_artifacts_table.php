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
        Schema::table('gne_artifacts', function (Blueprint $table) {
            $table->string('subject_identifier')->nullable()->after('scenario_identifier');
            $table->string('subject_type')->nullable()->after('subject_identifier');
            $table->index(['profile_identifier', 'scenario_identifier', 'subject_identifier', 'artifact_type'], 'gne_artifacts_subject_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gne_artifacts', function (Blueprint $table) {
            $table->dropIndex('gne_artifacts_subject_lookup');
            $table->dropColumn(['subject_identifier', 'subject_type']);
        });
    }
};
