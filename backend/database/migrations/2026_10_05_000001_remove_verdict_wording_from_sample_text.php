<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Members no longer see the bot's verdict, but the first sample notes and teasers
     * spelled it out ("worth a mention rather than a send"). Reword those samples only.
     */
    public function up(): void
    {
        DB::table('deals')
            ->where('is_sample', true)
            ->where('bot_note', 'like', 'Sample. Worth a mention rather than a send: %')
            ->update(['bot_note' => DB::raw("replace(bot_note, 'Sample. Worth a mention rather than a send: ', 'Sample. ')")]);

        DB::table('deals')
            ->where('is_sample', true)
            ->where('teaser', 'like', '% office worth a mention, with one or two open points.')
            ->update(['teaser' => DB::raw("replace(teaser, ' office worth a mention, with one or two open points.', ' office with one or two open points to check.')")]);
    }
};
