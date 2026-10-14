<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The administrator's own impression of a case's difficulty (1–5 stars), to tune the thresholds of the rating. */
return new class extends Migration
{
    protected $connection = 'whodoku';

    public function up(): void
    {
        Schema::create('difficulty_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('puzzle_id')->constrained('puzzles')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->timestamps();
            $table->unique(['user_id', 'puzzle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('difficulty_votes');
    }
};
