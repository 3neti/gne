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
        Schema::table('roster_generation_runs', function (Blueprint $table) {
            $table->date('policy_evaluation_date')->nullable()->after('policy_fingerprint');
            $table->json('policy_snapshot')->nullable()->after('policy_evaluation_date');
            $table->index(['roster_period_id', 'policy_evaluation_date']);
        });
        Schema::table('roster_policy_calibrations', function (Blueprint $table) {
            $table->string('decision_authority')->nullable()->after('confirmed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roster_policy_calibrations', function (Blueprint $table) {
            $table->dropColumn('decision_authority');
        });
        Schema::table('roster_generation_runs', function (Blueprint $table) {
            $table->dropIndex(['roster_period_id', 'policy_evaluation_date']);
            $table->dropColumn(['policy_evaluation_date', 'policy_snapshot']);
        });
    }
};
