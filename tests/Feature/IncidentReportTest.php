<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Whodoku\Models\Incident;
use Whodoku\Models\Puzzle;
use Whodoku\Models\User;

class IncidentReportTest extends TestCase
{
    use RefreshDatabase;

    private Puzzle $puzzle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->puzzle = Puzzle::on('whodoku')->create([
            'slug' => 'incident-test-case', 'title' => 'Тестовое дело', 'type' => 'murder', 'difficulty' => 1,
            'rows' => 4, 'cols' => 4, 'status' => 'published', 'collection' => 'custom', 'guest_access' => true, 'revision' => 3,
        ]);
    }

    protected function tearDown(): void
    {
        // The game keeps its own connection, which RefreshDatabase does not roll back.
        Incident::query()->where('puzzle_slug', 'incident-test-case')->delete();
        Puzzle::on('whodoku')->where('slug', 'incident-test-case')->delete();
        parent::tearDown();
    }

    public function test_a_guest_can_report_a_case_and_the_report_is_stored_as_new(): void
    {
        $this->postJson('/api/puzzles/incident-test-case/incidents', [
            'category' => 'unclear', 'message' => '  Непонятно,   где окно у Станислава ', 'placements' => ['a' => [0, 1]],
        ])->assertCreated();

        $incident = Incident::query()->where('puzzle_slug', 'incident-test-case')->sole();
        $this->assertSame('new', $incident->status);
        $this->assertSame('unclear', $incident->category);
        $this->assertSame('Непонятно, где окно у Станислава', $incident->message);
        $this->assertSame(3, $incident->puzzle_revision);
        $this->assertSame($this->puzzle->id, $incident->puzzle_id);
        $this->assertNull($incident->player_id);
        $this->assertSame(['a' => [0, 1]], $incident->context['placements']);
    }

    public function test_the_same_words_about_the_same_case_are_kept_once(): void
    {
        $body = ['category' => 'other', 'message' => 'Кнопка не нажимается'];

        $this->postJson('/api/puzzles/incident-test-case/incidents', $body)->assertCreated();
        $this->postJson('/api/puzzles/incident-test-case/incidents', $body)->assertCreated();

        $this->assertSame(1, Incident::query()->where('puzzle_slug', 'incident-test-case')->count());
    }

    public function test_a_report_without_a_kind_or_words_is_refused(): void
    {
        $this->postJson('/api/puzzles/incident-test-case/incidents', ['message' => 'Что-то не так'])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->postJson('/api/puzzles/incident-test-case/incidents', ['category' => 'other', 'message' => 'ой'])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->postJson('/api/puzzles/incident-test-case/incidents', ['category' => 'bogus', 'message' => 'Что-то не так'])->assertUnprocessable()->assertJsonValidationErrors('category');

        $this->assertSame(0, Incident::query()->where('puzzle_slug', 'incident-test-case')->count());
    }

    public function test_a_case_that_is_not_published_cannot_be_reported(): void
    {
        $this->puzzle->forceFill(['status' => 'draft'])->save();

        $this->postJson('/api/puzzles/incident-test-case/incidents', ['category' => 'other', 'message' => 'Что-то не так'])->assertNotFound();
    }

    public function test_the_number_of_reports_per_minute_is_limited(): void
    {
        foreach (range(1, 3) as $number) {
            $this->postJson('/api/puzzles/incident-test-case/incidents', ['category' => 'other', 'message' => "Сообщение номер $number"])->assertCreated();
        }

        $this->postJson('/api/puzzles/incident-test-case/incidents', ['category' => 'other', 'message' => 'Сообщение номер 4'])->assertTooManyRequests();
    }

    public function test_the_admin_sees_the_report_with_its_case_in_the_list_of_incidents(): void
    {
        $this->postJson('/api/puzzles/incident-test-case/incidents', ['category' => 'unsolvable', 'message' => 'Дело невозможно решить'])->assertCreated();
        $id = DB::connection('whodoku')->table('users')->insertGetId([
            'name' => 'Test Admin', 'email' => uniqid('admin-', true).'@example.test',
            'password' => Hash::make('test-password'), 'created_at' => now(), 'updated_at' => now(),
            'permissions' => json_encode(['platform.index' => true, 'platform.puzzles.validate' => true], JSON_THROW_ON_ERROR),
        ]);

        try {
            $this->actingAs(User::on('whodoku')->findOrFail($id), 'game_admin');

            $this->get('/admin/incidents')->assertOk()->assertSee('Дело невозможно решить')->assertSee('Тестовое дело')->assertSee('Дело нельзя решить');
            $this->get('/admin/incidents?status=resolved')->assertOk()->assertDontSee('Дело невозможно решить');
        } finally {
            DB::connection('whodoku')->table('users')->where('id', $id)->delete();
        }
    }
}
