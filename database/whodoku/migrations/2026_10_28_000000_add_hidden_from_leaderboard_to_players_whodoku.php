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
        Schema::table('players', function (Blueprint $table): void {
            $table->boolean('hidden_from_leaderboard')->default(false)->after('skills_reset_level');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn('hidden_from_leaderboard');
        });
    }
};
