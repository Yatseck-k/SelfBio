<?php

declare(strict_types=1);

namespace Whodoku\Database\Seeders;

use Illuminate\Database\Seeder;
use Orchid\Platform\Models\Role;

class KilldokuRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->roles() as $slug => $role) {
            Role::query()->updateOrCreate(['slug' => $slug], $role);
        }
    }

    /**
     * @return array<string, array{name: string, permissions: array<string, bool>}>
     */
    private function roles(): array
    {
        $viewer = [
            'platform.index' => true,
            'platform.puzzles.view' => true,
        ];
        $editor = [...$viewer, 'platform.puzzles.generate' => true, 'platform.puzzles.edit' => true, 'platform.puzzles.validate' => true];
        $administrator = [
            ...$editor,
            'platform.puzzles.publish' => true,
            'platform.puzzles.delete' => true,
            'platform.puzzles.objects' => true,
            'platform.puzzles.players' => true,
            'platform.systems.users' => true,
            'platform.systems.roles' => true,
        ];

        return [
            'owner' => ['name' => 'Owner', 'permissions' => $administrator],
            'admin' => ['name' => 'Admin', 'permissions' => $administrator],
            'editor' => ['name' => 'Editor', 'permissions' => $editor],
            'viewer' => ['name' => 'Viewer', 'permissions' => $viewer],
        ];
    }
}
