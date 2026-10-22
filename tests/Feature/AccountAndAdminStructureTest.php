<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User as AccountUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Whodoku\Models\User;

class AccountAndAdminStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_account_opens_on_its_first_tab_and_the_old_address_leads_there(): void
    {
        $this->get('/account')->assertRedirect('/login');
        $this->get('/account/repair')->assertRedirect('/login');

        $user = AccountUser::factory()->create();
        $this->actingAs($user);
        $this->get('/account')->assertRedirect('/account/repair');
        $this->get('/repair')->assertRedirect('/account/repair')->assertStatus(301);
    }

    public function test_the_repair_tab_sits_in_the_account_bar(): void
    {
        $root = storage_path('framework/testing/account-'.uniqid());
        File::ensureDirectoryExists($root.'/repair');
        File::put($root.'/repair/catalog.json', json_encode(['schema_version' => 1, 'sections' => []]));
        config()->set('services.repair.root', $root.'/repair');
        $this->actingAs(AccountUser::factory()->create());

        $html = $this->get('/account/repair')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->getContent();

        $this->assertStringContainsString('Личный кабинет', $html);
        $this->assertStringContainsString('href="/account/repair"', $html);
        $this->assertStringContainsString('action="/logout"', $html);
        File::deleteDirectory($root);
    }

    public function test_the_admin_menu_is_grouped_by_the_parts_of_the_site(): void
    {
        $this->actingAs($this->admin(), 'game_admin');

        $html = $this->get('/admin/main')->assertOk()->getContent();

        foreach (['Настройки сайта', 'Сайт-визитка', 'Настройки сайта-визитки', 'Игра Whodoku', 'Настройки игры', 'Личный кабинет', 'Доступ к админке'] as $label) {
            $this->assertStringContainsString($label, $html, $label);
        }
        $this->assertStringNotContainsString('Главная страница', $html);
        $this->assertStringNotContainsString('Приветствие', $html);
        $this->assertStringNotContainsString('/admin/selfbio/welcome', $html);
    }

    public function test_the_admin_menu_groups_open_and_close_and_the_main_page_is_short(): void
    {
        DB::connection('pgsql')->table('blog_posts')->insert(['title' => 'Т', 'slug' => 'dash', 'preview' => 'А', 'body' => 'Т', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->admin(), 'game_admin');

        $html = $this->get('/admin/main')->assertOk()->getContent();

        // The groups of the menu are lists that fold: their items are inside a collapse, not a flat run of links.
        $this->assertGreaterThanOrEqual(4, substr_count($html, 'data-bs-toggle="collapse"'));
        // The main page is cards with the numbers and the links; the long reference of the game is folded away.
        $this->assertStringContainsString('1 запись в блоге', $html);
        $this->assertStringContainsString('<summary', $html);
        $this->assertStringContainsString('Справка по игре Whodoku', $html);
        $this->assertStringNotContainsString('Разделы админки:', $html);
    }

    public function test_the_main_page_shows_only_the_cards_the_person_may_use(): void
    {
        $this->actingAs($this->admin(['platform.index' => true, 'platform.selfbio.blog.view' => true]), 'game_admin');

        $html = $this->get('/admin/main')->assertOk()->getContent();

        $this->assertStringContainsString('Сайт-визитка', $html);
        $this->assertStringNotContainsString('Справка по игре Whodoku', $html);
        $this->assertStringNotContainsString('Личный кабинет</h5>', $html);
    }

    public function test_account_users_are_managed_in_the_admin_panel(): void
    {
        $this->actingAs($this->admin(), 'game_admin');

        $this->get('/admin/account/users')->assertOk();
        $this->get('/admin/account/users/create')->assertOk();
        $this->post('/admin/account/users/create/save', ['user' => ['name' => 'Анна', 'email' => 'anna@example.test', 'password' => 'short']])
            ->assertSessionHasErrors('user.password');
        $this->post('/admin/account/users/create/save', ['user' => ['name' => 'Анна', 'email' => 'anna@example.test', 'password' => 'a-long-password-1']])->assertRedirect();

        $anna = AccountUser::query()->where('email', 'anna@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('a-long-password-1', $anna->password));
        $this->get('/admin/account/users')->assertOk()->assertSee('anna@example.test');

        // An empty password keeps the old one; the email stays unique.
        $this->post("/admin/account/users/{$anna->id}/edit/save", ['user' => ['name' => 'Анна К.', 'email' => 'anna@example.test', 'password' => '']])->assertRedirect();
        $this->assertTrue(Hash::check('a-long-password-1', $anna->refresh()->password));
        $this->assertSame('Анна К.', $anna->name);
        AccountUser::factory()->create(['email' => 'taken@example.test']);
        $this->post("/admin/account/users/{$anna->id}/edit/save", ['user' => ['name' => 'Анна', 'email' => 'taken@example.test', 'password' => '']])
            ->assertSessionHasErrors('user.email');

        $this->post('/admin/account/users/remove', ['user' => $anna->id])->assertRedirect('/admin/account/users');
        $this->assertDatabaseMissing('users', ['email' => 'anna@example.test'], 'pgsql');
    }

    public function test_a_user_made_in_the_admin_panel_signs_in_to_the_account(): void
    {
        $this->actingAs($this->admin(), 'game_admin');
        $this->post('/admin/account/users/create/save', ['user' => ['name' => 'Борис', 'email' => 'boris@example.test', 'password' => 'a-long-password-2']])->assertRedirect();
        auth('game_admin')->logout();
        auth()->shouldUse('web');

        $this->post('/login', ['email' => 'boris@example.test', 'password' => 'a-long-password-2'])->assertRedirect('/account');
        $this->assertAuthenticated('web');
    }

    public function test_the_repair_data_screen_shows_what_the_catalog_holds(): void
    {
        $root = storage_path('framework/testing/account-data-'.uniqid());
        File::ensureDirectoryExists($root);
        File::put($root.'/catalog.json', json_encode(['schema_version' => 1, 'sections' => [
            ['id' => '01_kitchen', 'messages' => [['id' => 1, 'attachments' => [['path' => 'a']], 'images' => [['path' => 'b']]], ['id' => 2]]],
        ]]));
        config()->set('services.repair.root', $root);
        $this->actingAs($this->admin(), 'game_admin');

        $this->get('/admin/account/repair')->assertOk()->assertSee('Кухня')->assertSee('2 сообщений и 2 вложений');
        File::deleteDirectory($root);
    }

    public function test_the_repair_data_screen_survives_a_broken_catalog(): void
    {
        $root = storage_path('framework/testing/account-broken-'.uniqid());
        File::ensureDirectoryExists($root);
        File::put($root.'/catalog.json', json_encode(['sections' => [['messages' => [['id' => 1]]], ['id' => 'unknown_section', 'messages' => []]]]));
        config()->set('services.repair.root', $root);
        $this->actingAs($this->admin(), 'game_admin');

        $this->get('/admin/account/repair')->assertOk()->assertSee('unknown_section');
        File::put($root.'/catalog.json', 'not json');
        $this->get('/admin/account/repair')->assertOk()->assertSee('Каталог пока пуст');
        File::deleteDirectory($root);
    }

    public function test_only_an_admin_with_the_account_permission_sees_the_account_screens(): void
    {
        $this->actingAs($this->admin(['platform.index' => true, 'platform.selfbio.blog.view' => true]), 'game_admin');

        $this->get('/admin/account/users')->assertForbidden();
        $this->get('/admin/account/repair')->assertForbidden();
        $this->post('/admin/account/users/create/save', ['user' => ['name' => 'X', 'email' => 'x@example.test', 'password' => 'a-long-password-1']])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.test'], 'pgsql');
    }

    /** @param  array<string, bool>|null  $permissions */
    private function admin(?array $permissions = null): User
    {
        $permissions ??= [
            'platform.index' => true,
            'platform.selfbio.settings.view' => true, 'platform.selfbio.settings.edit' => true,
            'platform.selfbio.contacts.view' => true, 'platform.selfbio.contacts.edit' => true,
            'platform.selfbio.blog.view' => true, 'platform.selfbio.blog.edit' => true,
            'platform.selfbio.account.view' => true, 'platform.selfbio.account.edit' => true,
            'platform.puzzles.view' => true, 'platform.systems.users' => true, 'platform.systems.roles' => true,
        ];
        $id = DB::connection('whodoku')->table('users')->insertGetId([
            'name' => 'Test Admin', 'email' => uniqid('admin-', true).'@example.test',
            'password' => Hash::make('test-password'), 'created_at' => now(), 'updated_at' => now(),
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);

        return User::on('whodoku')->findOrFail($id);
    }
}
