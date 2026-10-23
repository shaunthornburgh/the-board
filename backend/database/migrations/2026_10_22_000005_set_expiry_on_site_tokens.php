<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Token lifetime moves from Sanctum's global expiration to a per-token expires_at, so scout tokens
     * can be long-lived while site logins still last 30 days. Give existing site tokens their expiry.
     */
    public function up(): void
    {
        $minutes = (int) config('sanctum.site_token_minutes', 60 * 24 * 30);

        DB::table('personal_access_tokens')
            ->whereNull('expires_at')
            ->where('tokenable_type', 'App\\Models\\User')
            ->whereNot('name', 'like', 'scout:%')
            ->orderBy('id')
            ->each(fn ($token) => DB::table('personal_access_tokens')->where('id', $token->id)
                ->update(['expires_at' => Carbon::parse($token->created_at)->addMinutes($minutes)]));
    }

    public function down(): void
    {
        //
    }
};
