<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Strategy tags: a deal can carry several (PD and Potential HMO, say). development_type stays as the member-facing type.
        Schema::create('strategies', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('deal_strategy', function (Blueprint $table) {
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('strategy_id')->constrained()->cascadeOnDelete();
            $table->primary(['deal_id', 'strategy_id']);
        });

        // Seeded here, not in a seeder, so existing volumes get them too.
        DB::table('strategies')->insert(collect([
            'pd' => 'PD',
            'hmo' => 'HMO',
            'potential-hmo' => 'Potential HMO',
            'social-housing' => 'Social housing',
        ])->map(fn ($label, $slug) => ['slug' => $slug, 'label' => $label, 'created_at' => now(), 'updated_at' => now()])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_strategy');
        Schema::dropIfExists('strategies');
    }
};
