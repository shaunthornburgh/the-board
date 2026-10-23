<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Replaces the scouts' "seen" and short-code CSVs on Google Drive.
        Schema::create('scout_seen_listings', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('source_key');
            $table->string('source_url', 2048)->nullable();
            $table->string('scout')->nullable();
            $table->foreignId('deal_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['source', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scout_seen_listings');
    }
};
