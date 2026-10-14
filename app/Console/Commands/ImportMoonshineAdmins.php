<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Description('Import MoonShine administrators into the unified Orchid panel without replacing existing passwords')]
#[Signature('selfbio:import-admins {--apply : Write changes to the Whodoku administration schema}')]
class ImportMoonshineAdmins extends Command
{
    private const array PERMISSIONS = [
        'platform.index' => true,
        'platform.selfbio.blog.view' => true,
        'platform.selfbio.blog.edit' => true,
        'platform.selfbio.contacts.view' => true,
        'platform.selfbio.contacts.edit' => true,
        'platform.selfbio.welcome.view' => true,
        'platform.selfbio.welcome.edit' => true,
    ];

    public function handle(): int
    {
        $source = DB::connection('pgsql');
        $target = DB::connection('whodoku');
        $admins = $source->table('moonshine_users')
            ->join('moonshine_user_roles', 'moonshine_users.moonshine_user_role_id', '=', 'moonshine_user_roles.id')
            ->whereRaw('lower(moonshine_user_roles.name) = ?', ['admin'])
            ->select('moonshine_users.*')
            ->get();
        $skipped = $source->table('moonshine_users')->count() - $admins->count();
        $collisions = $admins->filter(fn ($admin) => $target->table('users')->whereRaw('lower(email) = ?', [mb_strtolower($admin->email)])->exists())->count();

        $this->line("MoonShine administrators: {$admins->count()}, existing Orchid accounts: {$collisions}, other roles skipped: {$skipped}.");
        if (!$this->option('apply')) {
            $this->line('Preview only. Use --apply after checking the database backup.');

            return self::SUCCESS;
        }

        $created = 0;
        $linked = 0;
        $target->transaction(function () use ($target, $admins, &$created, &$linked): void {
            $role = $target->table('roles')->where('slug', 'selfbio-admin')->first();
            $roleId = $role?->id ?? $target->table('roles')->insertGetId([
                'name' => 'SelfBio admin',
                'slug' => 'selfbio-admin',
                'permissions' => json_encode(self::PERMISSIONS, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($role) {
                $permissions = json_decode((string) $role->permissions, true) ?: [];
                $target->table('roles')->where('id', $roleId)->update([
                    'permissions' => json_encode($permissions + self::PERMISSIONS, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }

            foreach ($admins as $admin) {
                $user = $target->table('users')->whereRaw('lower(email) = ?', [mb_strtolower($admin->email)])->first();
                if (!$user) {
                    $userId = $target->table('users')->insertGetId([
                        'name' => $admin->name,
                        'email' => $admin->email,
                        'password' => $admin->password,
                        'created_at' => $admin->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                } else {
                    $userId = $user->id;
                }

                if (!$target->table('role_users')->where('user_id', $userId)->where('role_id', $roleId)->exists()) {
                    $target->table('role_users')->insert(['user_id' => $userId, 'role_id' => $roleId]);
                    $linked++;
                }
            }
        });

        $this->info("Imported: {$created} new accounts, {$linked} new role assignments. Existing Orchid passwords were preserved.");

        return self::SUCCESS;
    }
}
