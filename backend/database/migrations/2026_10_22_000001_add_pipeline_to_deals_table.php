<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->timestamp('rejected_at')->nullable()->after('status_changed_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');

            // Where a scout found it. source + source_key identify a listing (e.g. rightmove / 123456789).
            $table->string('scout')->nullable()->after('origin');
            $table->string('source')->nullable()->after('scout');
            $table->string('source_key')->nullable()->after('source');
            $table->text('scout_notes')->nullable()->after('source_url');
            $table->json('scout_payload')->nullable()->after('scout_notes');

            // The Analyst bot's raw result. Operator only; members see verdict/bot_note as curated by the operator.
            $table->string('analyst_verdict')->nullable()->index()->after('teaser');
            $table->text('analyst_notes')->nullable()->after('analyst_verdict');
            $table->timestamp('analyst_requested_at')->nullable()->after('analyst_notes');
            $table->timestamp('analyst_completed_at')->nullable()->after('analyst_requested_at');
            $table->text('analyst_error')->nullable()->after('analyst_completed_at');

            $table->unique(['source', 'source_key']);
        });

        // Drafts were entered and appraised by hand, so they are waiting for the operator's final decision.
        DB::table('deals')->where('status', 'draft')->update(['status' => 'awaiting_review']);
        DB::table('deals')->whereNull('status_changed_at')->update(['status_changed_at' => DB::raw('coalesce(published_at, updated_at, created_at)')]);
    }

    public function down(): void
    {
        DB::table('deals')->where('status', '!=', 'published')->update(['status' => 'draft']);

        Schema::table('deals', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_key']);
            $table->dropIndex(['analyst_verdict']);
            $table->dropColumn([
                'status_changed_at', 'rejected_at', 'rejection_reason',
                'scout', 'source', 'source_key', 'scout_notes', 'scout_payload',
                'analyst_verdict', 'analyst_notes', 'analyst_requested_at', 'analyst_completed_at', 'analyst_error',
            ]);
        });
    }
};
