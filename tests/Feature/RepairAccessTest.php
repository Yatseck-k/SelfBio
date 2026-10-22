<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RepairAccessTest extends TestCase
{
    use RefreshDatabase;

    private const string ATTACHMENT = '/Ремонт/00_planning_budget/attachments/message-1041341-file-01.pdf';

    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = storage_path('framework/testing/repair-access-'.uniqid());
        File::ensureDirectoryExists($this->fixtureRoot.'/repair/00_planning_budget/attachments');
        File::ensureDirectoryExists($this->fixtureRoot.'/repair/locales');
        File::ensureDirectoryExists($this->fixtureRoot.'/archive');
        File::put($this->fixtureRoot.'/repair/index.html', '<html><head></head><body>Repair</body></html>');
        File::put($this->fixtureRoot.'/repair/catalog.json', json_encode([
            'sections' => [
                ['id' => '00_planning_budget', 'messages' => [['id' => 1, 'text' => 'Plan']]],
                ['id' => '01_kitchen', 'messages' => []],
            ],
        ], JSON_THROW_ON_ERROR));
        File::put($this->fixtureRoot.'/repair/locales/ru.json', '{}');
        File::put($this->fixtureRoot.'/repair/00_planning_budget/attachments/message-1041341-file-01.pdf', '%PDF-1.4');
        File::put($this->fixtureRoot.'/archive/messages.html', '<html>Archive</html>');

        config()->set('services.repair.root', $this->fixtureRoot.'/repair');
        config()->set('services.repair.archive_root', $this->fixtureRoot.'/archive');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);
        parent::tearDown();
    }

    public function test_guest_is_redirected_from_catalog_and_attachments(): void
    {
        foreach (['/repair', '/api/catalog', '/Ремонт/locales/ru.json', '/archive/messages.html', self::ATTACHMENT] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
    }

    public function test_signed_in_user_can_read_and_update_catalog(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/account/repair')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/api/catalog')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/Ремонт/locales/ru.json')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/archive/messages.html')->assertOk();
        $this->get(self::ATTACHMENT)->assertOk();
        $this->postJson('/api/status', ['message_id' => 1, 'status' => 'inactive'])->assertOk();
        $this->assertSame('inactive', json_decode(File::get($this->fixtureRoot.'/repair/catalog.json'), true)['sections'][0]['messages'][0]['status']);
        $this->postJson('/api/move', ['message_id' => 1, 'target_section_id' => '01_kitchen'])->assertOk();
        $this->assertCount(1, json_decode(File::get($this->fixtureRoot.'/repair/catalog.json'), true)['sections'][1]['messages']);
        $this->postJson('/api/undo')->assertOk();
        $this->assertCount(1, json_decode(File::get($this->fixtureRoot.'/repair/catalog.json'), true)['sections'][0]['messages']);
    }

    public function test_paths_cannot_escape_the_catalog_directory(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/Ремонт/.env')->assertNotFound();
        $this->get('/archive/.env')->assertNotFound();
    }
}
