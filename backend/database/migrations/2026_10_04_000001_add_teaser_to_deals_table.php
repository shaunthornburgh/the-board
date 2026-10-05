<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The one line shown on the public index. The operator writes it; the bot does not send it.
        Schema::table('deals', function (Blueprint $table) {
            $table->string('teaser', 160)->nullable()->after('bot_note');
        });

        // Volumes seeded before each sample had its own photo still point at the shared placeholder.
        DB::table('deals')
            ->where('is_sample', true)
            ->where('photo_url', '/images/sample-building.svg')
            ->whereNotNull('reference')
            ->update(['photo_url' => DB::raw("'/images/deals/' || lower(reference) || '.jpg'")]);

        // ...and have no teaser line. Give them the same one a fresh seed writes.
        DB::table('deals')
            ->where('is_sample', true)
            ->whereNull('teaser')
            ->whereNotNull('town')
            ->update(['teaser' => DB::raw("case when verdict = 'send' then 'Office building in central ' || town || ' that reads as a Class MA candidate.' else town || ' office with one or two open points to check.' end")]);
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('teaser');
        });
    }
};
