<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'whodoku';

    public function up(): void
    {
        Schema::create('puzzles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('story')->nullable();
            $table->string('type');
            $table->unsignedTinyInteger('difficulty');
            $table->unsignedSmallInteger('rows');
            $table->unsignedSmallInteger('cols');
            // draft → published (open to players) | reserve (checked and hidden until a daily release)
            $table->string('status')->default('draft')->index();
            // `custom` (made one by one in the admin), `base` (the starting set) or `daily` (released on `released_on`).
            $table->string('collection', 16)->default('custom')->index();
            // Guests open only cases with this flag; hand-made cases stay open to everybody.
            $table->boolean('guest_access')->default(true);
            $table->date('released_on')->nullable()->index();
            $table->unsignedInteger('revision')->default(1);
            $table->jsonb('blocked_cells')->default('[]');
            // Windows stand on a side of a cell: [{"cell": [row, col], "side": "north|south|west|east"}].
            $table->jsonb('windows')->nullable();
            // The injured party: {"name", "role", "male", "avatar", "statement"}.
            $table->jsonb('victim')->nullable();
            $table->jsonb('ending')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('puzzle_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('puzzle_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->jsonb('cells');
            $table->timestamps();
            $table->unique(['puzzle_id', 'code']);
        });

        Schema::create('puzzle_walls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('puzzle_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('row_from');
            $table->unsignedSmallInteger('col_from');
            $table->unsignedSmallInteger('row_to');
            $table->unsignedSmallInteger('col_to');
            $table->timestamps();
            $table->unique(['puzzle_id', 'row_from', 'col_from', 'row_to', 'col_to'], 'puzzle_walls_endpoints_unique');
        });

        Schema::create('puzzle_objects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('puzzle_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('type');
            $table->string('name');
            $table->unsignedSmallInteger('row');
            $table->unsignedSmallInteger('col');
            $table->boolean('blocking');
            $table->boolean('occupiable');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['puzzle_id', 'code']);
        });

        Schema::create('puzzle_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('puzzle_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('kind');
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('counts_for_row_col')->default(true);
            $table->unsignedSmallInteger('sort_order');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['puzzle_id', 'code']);
        });

        Schema::create('puzzle_clues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('puzzle_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->string('constraint_type');
            $table->jsonb('constraint');
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['puzzle_id', 'sort_order']);
        });

        Schema::create('puzzle_solutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('puzzle_id')->unique()->constrained()->cascadeOnDelete();
            $table->jsonb('placements');
            $table->timestamps();
        });

        Schema::create('puzzle_hints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('puzzle_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->text('text');
            $table->unsignedSmallInteger('sort_order');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['puzzle_id', 'level']);
            $table->unique(['puzzle_id', 'sort_order']);
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('puzzle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_email')->nullable();
            $table->string('puzzle_slug')->nullable();
            $table->string('action');
            $table->jsonb('validation_result')->nullable();
            $table->jsonb('solver_result')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('puzzle_hints');
        Schema::dropIfExists('puzzle_solutions');
        Schema::dropIfExists('puzzle_clues');
        Schema::dropIfExists('puzzle_tokens');
        Schema::dropIfExists('puzzle_objects');
        Schema::dropIfExists('puzzle_walls');
        Schema::dropIfExists('puzzle_rooms');
        Schema::dropIfExists('puzzles');
    }
};
