<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->foreignId('development_type_id')->constrained();
            $table->string('status')->default('draft')->index();
            $table->string('origin')->default('manual');
            $table->boolean('is_sample')->default(false);

            $table->string('address')->nullable();
            $table->string('town')->nullable();
            $table->string('county')->nullable()->index();
            $table->string('postcode')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('photo_url')->nullable();

            $table->unsignedInteger('asking_price')->nullable();
            $table->string('tenure')->nullable();

            $table->string('source_name')->nullable();
            $table->string('source_url', 2048)->nullable();

            // The appraisal as sent by the bot. The app stores and displays it; it does not re-screen.
            $table->string('verdict')->nullable()->index();
            $table->text('bot_note')->nullable();
            $table->string('tenant')->nullable();
            $table->unsignedInteger('vacant_area_sq_ft')->nullable();
            $table->json('comps')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('appraised_at')->nullable();

            $table->timestamp('pack_generated_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
