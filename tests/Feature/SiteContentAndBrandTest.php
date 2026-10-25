<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Profile;
use App\Support\SiteName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;
use Whodoku\Models\User;

class SiteContentAndBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_screen_opens_when_the_contact_has_social_networks(): void
    {
        DB::connection('pgsql')->table('contact_info')->insert([
            'name' => 'Автор', 'email' => 'author@example.test',
            'socials' => json_encode([['name' => 'Telegram', 'url' => 'https://t.me/author']], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->admin(), 'game_admin');

        $this->get('/admin/selfbio/contacts')->assertOk()->assertSee('Telegram');
    }

    public function test_a_post_typed_in_the_site_time_zone_is_public_at_once(): void
    {
        config(['app.display_timezone' => 'Europe/Moscow']);
        $this->actingAs($this->admin(), 'game_admin');

        // The editor types «now» by the clock of Moscow; before the fix it was read as UTC and hidden for three hours.
        $this->post('/admin/selfbio/blog/create/save', ['post' => [
            'title' => 'Новая запись', 'slug' => 'new-post', 'preview' => 'Анонс', 'body' => 'Текст',
            'published_at' => now('Europe/Moscow')->format('Y-m-d\TH:i'),
        ]])->assertRedirect();

        $stored = DB::connection('pgsql')->table('blog_posts')->where('slug', 'new-post')->value('published_at');
        $this->assertEqualsWithDelta(now()->timestamp, strtotime($stored.' UTC'), 120);
        $this->getJson('/api/blog')->assertOk()->assertJsonPath('data.0.slug', 'new-post');
    }

    public function test_the_blog_form_shows_the_stored_time_in_the_site_time_zone(): void
    {
        config(['app.display_timezone' => 'Asia/Yekaterinburg']);
        $id = DB::connection('pgsql')->table('blog_posts')->insertGetId([
            'title' => 'Т', 'slug' => 'tz-check', 'preview' => 'А', 'body' => 'Т',
            'published_at' => '2026-01-10 10:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($this->admin(), 'game_admin');

        $this->get("/admin/selfbio/blog/{$id}/edit")->assertOk()->assertSeeHtml('2026-01-10T15:00');
    }

    public function test_the_site_name_goes_to_the_pages_and_the_admin_panel(): void
    {
        config(['app.name' => 'Мой дом']);
        $this->get('/blog')->assertInertia(fn (AssertableInertia $page) => $page->where('brand', 'Мой дом'));

        $this->actingAs($this->admin(), 'game_admin');
        $this->get('/admin/main')->assertOk()->assertSee('Мой дом');
    }

    public function test_the_saved_name_replaces_app_name_when_the_app_starts(): void
    {
        config(['app.name' => 'SelfBio']);
        SiteName::apply();
        $this->assertSame('SelfBio', config('app.name'));

        SiteName::save('Мой дом');
        config(['app.name' => 'SelfBio']);
        Cache::flush();
        SiteName::apply();
        $this->assertSame('Мой дом', config('app.name'));

        SiteName::reset();
        config(['app.name' => 'SelfBio']);
        SiteName::apply();
        $this->assertSame('SelfBio', config('app.name'));
    }

    public function test_a_reset_goes_back_to_the_name_of_the_environment_even_from_a_cached_config(): void
    {
        // A cached config can hold the name that was saved when it was built.
        config(['app.default_name' => 'SelfBio', 'app.name' => 'Студия']);

        SiteName::reset();
        $this->assertSame('SelfBio', config('app.name'));

        config(['app.name' => 'Студия']);
        SiteName::apply();
        $this->assertSame('SelfBio', config('app.name'));
    }

    public function test_the_name_is_changed_and_reset_in_the_admin_panel(): void
    {
        $this->actingAs($this->admin(), 'game_admin');

        $this->get('/admin/selfbio/settings')->assertOk()->assertSee('Название сайта')->assertDontSee('Тексты страниц');
        $this->post('/admin/selfbio/settings/save', ['site' => ['name' => '  Моя студия  ']])->assertRedirect('/admin/selfbio/settings');
        $this->assertDatabaseHas('site_settings', ['key' => 'site.name', 'value' => 'Моя студия'], 'pgsql');
        $this->get('/admin/main')->assertOk()->assertSee('Моя студия');
        $this->get('/blog')->assertInertia(fn (AssertableInertia $page) => $page->where('brand', 'Моя студия'));

        $this->post('/admin/selfbio/settings/save', ['site' => ['name' => '']])->assertSessionHasErrors('site.name');
        $this->post('/admin/selfbio/settings/save', ['site' => ['name' => str_repeat('я', 61)]])->assertSessionHasErrors('site.name');
        $this->assertDatabaseHas('site_settings', ['value' => 'Моя студия'], 'pgsql');

        $this->post('/admin/selfbio/settings/reset')->assertRedirect('/admin/selfbio/settings');
        $this->assertDatabaseMissing('site_settings', ['key' => 'site.name'], 'pgsql');
    }

    public function test_page_texts_are_edited_in_the_admin_panel_and_fall_back_to_defaults(): void
    {
        $this->get('/blog')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageTexts.blog.title', 'Блог')
            ->where('pageTexts.contacts.title', 'Контактная информация')
            ->where('pageTexts.contacts.lead', ''));

        $this->actingAs($this->admin(), 'game_admin');
        $this->get('/admin/selfbio/card-settings')->assertOk()->assertSee('Контактная информация')->assertDontSee('Игра: что это за страница');
        $this->get('/admin/selfbio/game-settings')->assertOk()->assertSee('Игра: что это за страница')->assertDontSee('Блог: заголовок страницы');
        $this->post('/admin/selfbio/card-settings/save', ['texts' => [
            'contacts' => ['title' => 'Как нас найти', 'lead' => 'Пишите, отвечаем в течение дня.'],
            'blog' => ['title' => 'Заметки', 'lead' => ''],
            'puzzles' => ['about' => 'Это поле не из этого экрана и не сохранится.'],
        ]])->assertRedirect('/admin/selfbio/card-settings');
        $this->post('/admin/selfbio/game-settings/save', ['texts' => ['puzzles' => ['about' => 'Игра для тех, кто любит детективы.']]])
            ->assertRedirect('/admin/selfbio/game-settings');

        $this->get('/contacts')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageTexts.contacts.title', 'Как нас найти')
            ->where('pageTexts.contacts.lead', 'Пишите, отвечаем в течение дня.')
            ->where('pageTexts.blog.title', 'Заметки')
            ->where('pageTexts.puzzles.about', 'Игра для тех, кто любит детективы.'));
        // The same texts reach the game pages.
        $this->get('/puzzles')->assertInertia(fn (AssertableInertia $page) => $page->where('pageTexts.puzzles.about', 'Игра для тех, кто любит детективы.'));

        // An empty field goes back to the default; a default typed again is not stored.
        $this->post('/admin/selfbio/card-settings/save', ['texts' => ['contacts' => ['title' => '', 'lead' => ''], 'blog' => ['title' => 'Блог']]])
            ->assertRedirect();
        $this->get('/blog')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pageTexts.contacts.title', 'Контактная информация')
            ->where('pageTexts.blog.title', 'Блог'));
        $this->assertDatabaseMissing('site_settings', ['key' => 'page.contacts.title'], 'pgsql');
        $this->assertDatabaseMissing('site_settings', ['key' => 'page.blog.title'], 'pgsql');
    }

    public function test_profile_content_and_blocks_are_edited_for_both_languages(): void
    {
        $this->actingAs($this->admin(), 'game_admin');
        $this->get('/admin/selfbio/profile')->assertOk()->assertSee('Карточки компетенций')->assertSee('English');

        $ru = Profile::editor('ru');
        $en = Profile::editor('en');
        $ru['name'] = 'Проверочное имя';
        $ru['lead'] = 'Новый текст главной.';
        $ru['about_lead'] = 'Новое вступление обо мне.';
        $ru['focus_enabled'] = '0';
        $ru['hero_tags'] = null;
        $ru['focus_order'] = 4;
        $ru['contact_order'] = 1;
        $ru['writing_title'] = 'Новые статьи';
        $en['name'] = 'Test name';
        $en['focus'][0]['title'] = 'Edited focus';

        $this->post('/admin/selfbio/profile/save', ['profile' => ['ru' => $ru, 'en' => $en]])
            ->assertRedirect('/admin/selfbio/profile');
        $this->assertDatabaseHas('site_settings', ['key' => 'profile.ru'], 'pgsql');
        $this->assertDatabaseHas('site_settings', ['key' => 'profile.en'], 'pgsql');
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('profile.name', 'Проверочное имя')
            ->where('profile.lead', 'Новый текст главной.')
            ->where('profile.focus_enabled', false)
            ->where('profile.hero_tags', [])
            ->where('profile.contact_order', 1)
            ->where('profile.writing_title', 'Новые статьи'));
        $this->get('/en')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('profile.name', 'Test name')
            ->where('profile.focus.0.title', 'Edited focus'));
        $this->assertStringContainsString('Проверочное имя', $this->get('/')->getContent());
        $this->assertStringContainsString('Новое вступление обо мне.', $this->get('/about')->getContent());
        $this->assertStringNotContainsString('Архитектура backend', $this->get('/')->getContent());

        $this->post('/admin/selfbio/profile/reset', ['locale' => 'ru'])->assertRedirect('/admin/selfbio/profile');
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->where('profile.name', 'Антон Яценко'));
        $this->get('/en')->assertInertia(fn (AssertableInertia $page) => $page->where('profile.name', 'Test name'));
    }

    public function test_profile_editor_rejects_unauthorized_changes(): void
    {
        $this->actingAs($this->admin(['platform.index' => true]), 'game_admin');
        $this->get('/admin/selfbio/profile')->assertForbidden();
        $this->post('/admin/selfbio/profile/save', ['profile' => []])->assertForbidden();
    }

    public function test_profile_editor_rejects_invalid_content(): void
    {
        $this->actingAs($this->admin(), 'game_admin');
        $ru = Profile::editor('ru');
        $en = Profile::editor('en');
        $ru['name'] = '';
        $this->post('/admin/selfbio/profile/save', ['profile' => ['ru' => $ru, 'en' => $en]])
            ->assertSessionHasErrors('profile.ru.name');
        $ru['name'] = 'Антон Яценко';
        $ru['focus_order'] = 2;
        $this->post('/admin/selfbio/profile/save', ['profile' => ['ru' => $ru, 'en' => $en]])
            ->assertSessionHasErrors('profile.ru.focus_order');
        $this->assertDatabaseMissing('site_settings', ['key' => 'profile.ru'], 'pgsql');
    }

    public function test_a_session_lives_a_week_by_default(): void
    {
        // The environment of a machine may set its own value; the defaults of the project are a week (10080 minutes).
        $this->assertStringContainsString('SESSION_LIFETIME=10080', (string) file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString("env('SESSION_LIFETIME', 10080)", (string) file_get_contents(config_path('session.php')));
    }

    public function test_only_an_admin_with_the_settings_permission_can_change_the_name(): void
    {
        $this->actingAs($this->admin(['platform.index' => true, 'platform.selfbio.blog.view' => true]), 'game_admin');

        $this->get('/admin/selfbio/settings')->assertForbidden();
        $this->post('/admin/selfbio/settings/save', ['site' => ['name' => 'Чужое']])->assertForbidden();
        $this->assertDatabaseMissing('site_settings', ['key' => 'site.name'], 'pgsql');
    }

    public function test_site_rename_command_uses_the_same_setting(): void
    {
        $this->artisan('site:rename', ['name' => 'Мой дом'])->assertExitCode(0);
        $this->assertDatabaseHas('site_settings', ['key' => 'site.name', 'value' => 'Мой дом'], 'pgsql');
        $this->artisan('site:rename', ['name' => "Плохое\x07имя"])->assertExitCode(1);
        $this->artisan('site:rename', ['--show' => true])->expectsOutputToContain('Мой дом')->assertExitCode(0);
        $this->artisan('site:rename', ['--reset' => true])->assertExitCode(0);
        $this->assertDatabaseMissing('site_settings', ['key' => 'site.name'], 'pgsql');
    }

    /** @param  array<string, bool>|null  $permissions */
    private function admin(?array $permissions = null): User
    {
        $permissions ??= [
            'platform.index' => true,
            'platform.selfbio.blog.view' => true, 'platform.selfbio.blog.edit' => true,
            'platform.selfbio.contacts.view' => true, 'platform.selfbio.contacts.edit' => true,
            'platform.selfbio.settings.view' => true, 'platform.selfbio.settings.edit' => true,
        ];
        $id = DB::connection('whodoku')->table('users')->insertGetId([
            'name' => 'Test Admin', 'email' => uniqid('admin-', true).'@example.test',
            'password' => Hash::make('test-password'), 'created_at' => now(), 'updated_at' => now(),
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);

        return User::on('whodoku')->findOrFail($id);
    }
}
