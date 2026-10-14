<?php

declare(strict_types=1);

namespace Whodoku\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Whodoku\Domain\Puzzle\Generation\ObjectCatalog;
use Whodoku\Models\ObjectKind;
use Whodoku\Models\ObjectName;
use Whodoku\Models\RoomTheme;

/**
 * The vocabulary the generator has always used (kinds of furniture, their names, the furniture of each room) as the
 * starting point of the lists the administrator edits. It fills an empty catalog only, so a re-run (the container
 * seeds on every start) never undoes the edits.
 */
class ObjectCatalogSeeder extends Seeder
{
    public function run(): void
    {
        if (ObjectKind::query()->exists() || RoomTheme::query()->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $defaults = ObjectCatalog::defaults();
            foreach ($defaults->kinds() as $code => $kind) {
                $model = ObjectKind::query()->create([
                    'code' => $code, 'label' => $kind['label'], 'icon' => $kind['icon'] ?? '▫️', 'occupiable' => $kind['occupiable'],
                    'standing_phrase' => $kind['on'] ?? null, 'active' => true,
                ]);
                foreach ($kind['names'] as $name) {
                    ObjectName::query()->create(['object_kind_id' => $model->id, 'name' => $name, 'active' => true]);
                }
            }
            foreach ($defaults->themes() as $name => $kinds) {
                RoomTheme::query()->create(['name' => $name, 'kinds' => $kinds, 'active' => true]);
            }
        });
    }
}
