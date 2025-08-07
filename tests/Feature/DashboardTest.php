<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_dashboard_requires_authentication()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_dashboard()
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_dashboard_stats_api_returns_correct_data()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tasks' => ['total', 'completed_today', 'pending', 'overdue'],
                'pomodoro' => ['today_sessions', 'today_minutes', 'week_sessions'],
                'calendar' => ['today_events', 'upcoming_week'],
            ]);
    }

    public function test_weather_api_returns_data()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/weather/current');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'city', 'temperature', 'description', 'humidity',
            ]);
    }

    public function test_user_can_create_task()
    {
        $taskData = [
            'title' => 'Test Task',
            'description' => 'Test Description',
            'priority' => 'high',
            'due_date' => now()->addDays(1)->toDateString(),
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/tasks', $taskData);

        $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'Test Task']);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Test Task',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_create_calendar_event()
    {
        $eventData = [
            'title' => 'Test Event',
            'description' => 'Test Description',
            'start_date' => now()->addHour()->toISOString(),
            'end_date' => now()->addHours(2)->toISOString(),
            'color' => '#3B82F6',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/calendar-events', $eventData);

        $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'Test Event']);
    }

    public function test_user_can_start_pomodoro_session()
    {
        $sessionData = [
            'duration' => 25,
            'type' => 'work',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/pomodoro', $sessionData);

        $response->assertStatus(201)
            ->assertJsonFragment(['duration' => 25]);
    }

    public function test_user_can_create_quick_link()
    {
        $linkData = [
            'title' => 'Test Link',
            'url' => 'https://example.com',
            'icon' => '🔗',
            'category' => 'Test',
            'color' => '#3B82F6',
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/quick-links', $linkData);

        $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'Test Link']);
    }
}
