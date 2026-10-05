<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Uploaded photos are now served from /api/photos. The files themselves do not move. */
    public function up(): void
    {
        DB::table('deals')
            ->where('photo_url', 'like', '/photos/%')
            ->update(['photo_url' => DB::raw("'/api' || photo_url")]);
    }

    public function down(): void
    {
        DB::table('deals')
            ->where('photo_url', 'like', '/api/photos/%')
            ->update(['photo_url' => DB::raw('substr(photo_url, 5)')]);
    }
};
