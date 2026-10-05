<?php

namespace Database\Seeders;

use App\Models\DevelopmentType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Only permitted development is seeded; the model allows other types later.
        DevelopmentType::updateOrCreate(['slug' => 'pd'], ['label' => 'Permitted development']);

        User::updateOrCreate(['email' => 'operator@theboard.test'], [
            'name' => 'Board Operator',
            'password' => 'password',
            'role' => User::ROLE_OPERATOR,
        ]);

        User::updateOrCreate(['email' => 'member@theboard.test'], [
            'name' => 'Sample Member',
            'password' => 'password',
            'role' => User::ROLE_MEMBER,
            'membership_status' => User::STATUS_ACTIVE,
            'invited_at' => now()->subMonths(2),
            'activated_at' => now()->subMonths(2)->addDays(3),
        ]);

        $this->call(SampleDealSeeder::class);
    }
}
