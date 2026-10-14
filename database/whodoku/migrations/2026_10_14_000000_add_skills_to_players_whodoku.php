<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Development of the detective: development points put into skills, and when they were last redistributed. */
return new class extends Migration
{
    protected $connection = 'whodoku';

    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->jsonb('skills')->nullable()->after('points');
            $table->timestamp('skills_reset_at')->nullable()->after('skills');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropColumn(['skills', 'skills_reset_at']);
        });
    }
};
