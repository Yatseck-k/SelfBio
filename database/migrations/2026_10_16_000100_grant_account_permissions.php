<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array NEW_PERMISSIONS = ['platform.selfbio.account.view', 'platform.selfbio.account.edit'];

    /** Those who already change the settings of the site may also manage the users of the personal account. */
    public function up(): void
    {
        $admin = DB::connection('whodoku');
        foreach (['users', 'roles'] as $name) {
            if (!Schema::connection('whodoku')->hasTable($name)) {
                continue;
            }
            foreach ($admin->table($name)->whereNotNull('permissions')->get(['id', 'permissions']) as $row) {
                $permissions = json_decode((string) $row->permissions, true);
                if (!is_array($permissions) || empty($permissions['platform.selfbio.settings.edit'])) {
                    continue;
                }
                foreach (self::NEW_PERMISSIONS as $permission) {
                    $permissions[$permission] = true;
                }
                $admin->table($name)->where('id', $row->id)->update(['permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void {}
};
