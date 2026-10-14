<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            if (app()->environment('testing') && str_starts_with((string) DB::getDatabaseName(), 'testing')) {
                DB::statement('DROP SCHEMA IF EXISTS whodoku CASCADE');
            }
            DB::statement('CREATE SCHEMA IF NOT EXISTS whodoku');
        }
    }

    public function down(): void
    {
        // Preserve game data on rollback; dropping the schema requires a deliberate data migration.
    }
};
