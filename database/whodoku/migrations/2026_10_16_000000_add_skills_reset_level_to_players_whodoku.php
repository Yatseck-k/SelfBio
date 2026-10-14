<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The level a player had when they took their skill points back: the next redistribution waits for twenty more levels. */
return new class extends Migration
{
    protected $connection = 'whodoku';

    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->unsignedSmallInteger('skills_reset_level')->nullable()->after('skills_reset_at');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('skills_reset_level');
        });
    }
};
