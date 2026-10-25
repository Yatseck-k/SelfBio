<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reports of players about a case («Сообщить об ошибке»): kept until an admin has dealt with them. */
return new class extends Migration
{
    protected $connection = 'whodoku';

    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            // The case may be replaced or deleted later; the slug and the revision say what the player saw.
            $table->foreignId('puzzle_id')->nullable()->constrained('puzzles')->nullOnDelete();
            $table->string('puzzle_slug', 120)->index();
            $table->unsignedInteger('puzzle_revision')->default(1);
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('category', 24);
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('reporter_hash', 64)->index();
            $table->string('status', 16)->default('new')->index();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['puzzle_slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
