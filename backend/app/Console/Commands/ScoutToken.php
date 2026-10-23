<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('board:scout-token {name : The scout, e.g. rightmove-scout} {--days= : Expire the token after this many days (default: never)} {--revoke : Delete this scout\'s tokens instead}')]
#[Description('Issue (or revoke) a service token a scout uses to push candidates and mark listings seen')]
class ScoutToken extends Command
{
    public function handle(): int
    {
        $name = Str::slug($this->argument('name'));
        $email = "{$name}@scouts.theboard.internal";

        if ($this->option('revoke')) {
            $user = User::where('email', $email)->first();
            $count = $user?->tokens()->delete() ?? 0;
            $this->info("Revoked {$count} token(s) for {$name}.");

            return self::SUCCESS;
        }

        // A service account that cannot sign in: random password, service role.
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make(Str::random(64)),
            'role' => User::ROLE_SERVICE,
        ]);

        $days = $this->option('days');
        $token = $user->createToken("scout:{$name}", ['scout:write'], $days ? now()->addDays((int) $days) : null);

        $this->info("Token for {$name} (shown once, store it with the scout):");
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
