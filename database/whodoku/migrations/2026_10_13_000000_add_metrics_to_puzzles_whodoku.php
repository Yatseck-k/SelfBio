<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What the difficulty rating was measured on (logic, clarity, size, conditions); recomputed by `puzzles:rate`. */
return new class extends Migration
{
    protected $connection = 'whodoku';

    public function up(): void
    {
        Schema::table('puzzles', function (Blueprint $table): void {
            $table->jsonb('metrics')->nullable()->after('difficulty');
        });
    }

    public function down(): void
    {
        Schema::table('puzzles', function (Blueprint $table): void {
            $table->dropColumn('metrics');
        });
    }
};
