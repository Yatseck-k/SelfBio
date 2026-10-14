<?php

declare(strict_types=1);

namespace Whodoku\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /** Everything here is safe to run again: the container seeds on every start. */
    public function run(): void
    {
        $originalConnection = DB::getDefaultConnection();
        DB::setDefaultConnection('whodoku');

        try {
            $this->call([
                KilldokuRoleSeeder::class,
                ObjectCatalogSeeder::class,
            ]);
        } finally {
            DB::setDefaultConnection($originalConnection);
        }
    }
}
