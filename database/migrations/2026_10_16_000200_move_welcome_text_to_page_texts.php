<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The «Главная страница» screen is gone: its title and text were the heading and the explanation of the home page,
     * which are now the texts of the «Контакты» page. What the editors wrote is kept there (the table `welcome` stays).
     */
    public function up(): void
    {
        if (!Schema::hasTable('welcome') || !Schema::hasTable('site_settings')) {
            return;
        }
        $welcome = DB::table('welcome')->first();
        if ($welcome === null) {
            return;
        }

        foreach (['page.contacts.title' => $welcome->title, 'page.contacts.lead' => $welcome->body] as $key => $value) {
            if (trim((string) $value) !== '' && !DB::table('site_settings')->where('key', $key)->exists()) {
                DB::table('site_settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void {}
};
