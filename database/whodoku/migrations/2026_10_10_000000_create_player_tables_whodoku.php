<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'whodoku';

    /** Players sign in with Telegram and play as a detective; their results feed the rating, hints and statistics. */
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            // Telegram ids are positive; ids below zero belong to the local test login.
            $table->bigInteger('telegram_id')->unique();
            $table->string('telegram_username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('detective_name', 40)->nullable();
            // Lower-cased name: unique whatever the database does with Cyrillic capitals.
            $table->string('detective_key', 40)->nullable()->unique();
            $table->string('avatar')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->timestamp('banned_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('player_case_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('puzzle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('puzzle_slug');
            $table->string('puzzle_title');
            $table->string('puzzle_type');
            $table->unsignedTinyInteger('difficulty');
            $table->unsignedInteger('revision');
            $table->timestamp('first_opened_at');
            $table->timestamp('solved_at')->nullable()->index();
            $table->unsignedInteger('checks_count')->default(0);
            $table->unsignedInteger('hints_used')->default(0);
            $table->unsignedInteger('points_awarded')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
            $table->unique(['player_id', 'puzzle_slug', 'revision']);
        });

        Schema::create('player_hint_uses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('puzzle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('puzzle_slug');
            $table->unsignedInteger('revision');
            $table->unsignedTinyInteger('level');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['player_id', 'puzzle_slug', 'revision', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_hint_uses');
        Schema::dropIfExists('player_case_results');
        Schema::dropIfExists('players');
    }
};
