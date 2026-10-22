<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array NEW_PERMISSIONS = ['platform.selfbio.settings.view', 'platform.selfbio.settings.edit'];

    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Those who already edit the content of the site may also open its settings.
        $admin = DB::connection('whodoku');
        foreach (['users', 'roles'] as $name) {
            if (!Schema::connection('whodoku')->hasTable($name)) {
                continue;
            }
            foreach ($admin->table($name)->whereNotNull('permissions')->get(['id', 'permissions']) as $row) {
                $permissions = json_decode((string) $row->permissions, true);
                if (!is_array($permissions) || empty($permissions['platform.selfbio.welcome.edit'])) {
                    continue;
                }
                foreach (self::NEW_PERMISSIONS as $permission) {
                    $permissions[$permission] = true;
                }
                $admin->table($name)->where('id', $row->id)->update(['permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
