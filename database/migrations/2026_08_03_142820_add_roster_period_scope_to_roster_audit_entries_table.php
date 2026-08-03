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
        Schema::table('roster_audit_entries', function (Blueprint $table) {
            $table->foreignId('roster_period_id')->nullable()->after('actor_id')->constrained('roster_periods')->nullOnDelete();
            $table->index(['roster_period_id', 'created_at']);
            $table->index(['roster_period_id', 'action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roster_audit_entries', function (Blueprint $table) {
            $table->dropIndex(['roster_period_id', 'action', 'created_at']);
            $table->dropIndex(['roster_period_id', 'created_at']);
            $table->dropConstrainedForeignId('roster_period_id');
        });
    }
};
