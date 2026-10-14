<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The case that tells a chapter of the story «Путь детектива» (collection `story`); empty for all other cases. */
return new class extends Migration
{
    protected $connection = 'whodoku';

    public function up(): void
    {
        Schema::table('puzzles', function (Blueprint $table): void {
            $table->unsignedTinyInteger('story_chapter')->nullable()->unique()->after('collection');
        });
    }

    public function down(): void
    {
        Schema::table('puzzles', function (Blueprint $table): void {
            $table->dropUnique(['story_chapter']);
            $table->dropColumn('story_chapter');
        });
    }
};
