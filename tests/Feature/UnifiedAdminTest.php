<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Whodoku\Models\User;

class UnifiedAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_single_admin_login(): void
    {
        $this->get('/admin/selfbio/blog')->assertRedirect('/admin/login');
        $this->get('/game-admin/login')->assertRedirect('/admin/login');
    }

    public function test_game_only_admin_cannot_open_selfbio_screens(): void
    {
        $this->actingAs($this->admin(['platform.index' => true, 'platform.puzzles.view' => true]), 'game_admin');

        $this->get('/admin/main')->assertOk();
        $this->get('/admin/puzzles')->assertOk();
        $this->get('/admin/selfbio/blog')->assertForbidden();
        $this->post('/admin/selfbio/contacts/save', ['contact' => ['name' => 'X', 'email' => 'x@example.test']])
            ->assertForbidden();
    }

    public function test_selfbio_admin_can_save_public_content(): void
    {
        Storage::fake('public');
        $admin = $this->admin([
            'platform.index' => true,
            'platform.selfbio.blog.view' => true,
            'platform.selfbio.blog.edit' => true,
            'platform.selfbio.contacts.view' => true,
            'platform.selfbio.contacts.edit' => true,
            'platform.selfbio.welcome.view' => true,
            'platform.selfbio.welcome.edit' => true,
        ]);
        $this->assertTrue($admin->hasAccess('platform.selfbio.blog.view'));
        $this->actingAs($admin, 'game_admin');

        $this->get('/admin/selfbio/blog')->assertOk();
        $this->get('/admin/selfbio/contacts')->assertOk();
        $this->get('/admin/selfbio/welcome')->assertOk();
        $this->get('/admin/main')->assertOk()->assertSee('SelfBio')->assertDontSee('Предметы на карте');

        $this->post('/admin/selfbio/contacts/save', ['contact' => [
            'name' => 'SelfBio', 'email' => 'contact@example.test', 'socials' => '[]',
        ]])->assertRedirect('/admin/selfbio/contacts');
        $this->assertDatabaseHas('contact_info', ['email' => 'contact@example.test'], 'pgsql');

        $this->post('/admin/selfbio/welcome/save', ['welcome' => [
            'title' => 'Добро пожаловать', 'body' => 'Текст страницы',
        ]])->assertRedirect('/admin/selfbio/welcome');
        $this->assertDatabaseHas('welcome', ['title' => 'Добро пожаловать'], 'pgsql');

        $this->post('/admin/selfbio/blog/create/save', ['post' => [
            'title' => 'Статья', 'slug' => 'article', 'preview' => 'Анонс',
            'body' => 'Текст', 'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ], 'image_upload' => UploadedFile::fake()->image('cover.jpg')])->assertRedirect();
        $this->assertDatabaseHas('blog_posts', ['slug' => 'article'], 'pgsql');
        $image = DB::connection('pgsql')->table('blog_posts')->where('slug', 'article')->value('image');
        $this->assertNotNull($image);
        Storage::disk('public')->assertExists($image);

        $postId = DB::connection('pgsql')->table('blog_posts')->where('slug', 'article')->value('id');
        $this->get('/admin/selfbio/blog')->assertOk();
        $this->get("/admin/selfbio/blog/{$postId}/edit")->assertOk();
        $this->post("/admin/selfbio/blog/{$postId}/edit/save", ['post' => [
            'title' => 'Обновлённая статья', 'slug' => 'article', 'preview' => 'Анонс',
            'body' => 'Текст', 'published_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ]])->assertRedirect("/admin/selfbio/blog/{$postId}/edit");
        $this->assertDatabaseHas('blog_posts', ['id' => $postId, 'title' => 'Обновлённая статья'], 'pgsql');

        $this->getJson('/api/contacts')->assertOk()->assertJsonPath('email', 'contact@example.test');
        $this->getJson('/api/welcome')->assertOk()->assertJsonPath('title', 'Добро пожаловать');
        $this->getJson('/api/blog/article')->assertOk()->assertJsonPath('title', 'Обновлённая статья');
    }

    public function test_moonshine_import_keeps_existing_orchid_password_and_is_idempotent(): void
    {
        $admin = $this->admin(['platform.index' => true]);
        $password = $admin->password;
        $source = DB::connection('pgsql');
        $roleId = $source->table('moonshine_user_roles')->value('id');
        $source->table('moonshine_users')->insert([
            'name' => 'Old Admin', 'email' => $admin->email,
            'password' => Hash::make('old-password'), 'moonshine_user_role_id' => $roleId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('selfbio:import-admins', ['--apply' => true])->assertExitCode(0);
        $this->artisan('selfbio:import-admins', ['--apply' => true])->assertExitCode(0);

        $target = DB::connection('whodoku');
        $role = $target->table('roles')->where('slug', 'selfbio-admin')->first();
        $this->assertNotNull($role);
        $this->assertEquals(1, $target->table('role_users')->where('user_id', $admin->id)->where('role_id', $role->id)->count());
        $this->assertSame($password, $target->table('users')->where('id', $admin->id)->value('password'));
    }

    public function test_game_admin_screens_remain_available(): void
    {
        $permissions = ['platform.index' => true, 'platform.systems.users' => true, 'platform.systems.roles' => true];
        foreach (['view', 'edit', 'validate', 'publish', 'delete', 'generate', 'objects', 'players'] as $permission) {
            $permissions['platform.puzzles.'.$permission] = true;
        }
        $this->actingAs($this->admin($permissions), 'game_admin');

        foreach (['main', 'puzzles', 'puzzles/generate', 'objects', 'appearance', 'release', 'players', 'orders', 'difficulty', 'skills', 'users', 'roles'] as $path) {
            $this->get('/admin/'.$path)->assertOk();
        }

        $puzzleId = DB::connection('whodoku')->table('puzzles')->insertGetId([
            'slug' => 'admin-screen-check', 'title' => 'Проверка экрана', 'type' => 'murder',
            'difficulty' => 1, 'rows' => 4, 'cols' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (["puzzles/{$puzzleId}", "puzzles/{$puzzleId}/map", "puzzles/{$puzzleId}/validate"] as $path) {
            $this->get('/admin/'.$path)->assertOk();
        }
    }

    private function admin(array $permissions): User
    {
        $id = DB::connection('whodoku')->table('users')->insertGetId([
            'name' => 'Test Admin', 'email' => uniqid('admin-', true).'@example.test',
            'password' => Hash::make('test-password'), 'created_at' => now(), 'updated_at' => now(),
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
        ]);

        return User::on('whodoku')->findOrFail($id);
    }
}
