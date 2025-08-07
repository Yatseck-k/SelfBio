<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_weather_api_returns_mock_data_without_api_key()
    {
        config(['services.openweathermap.key' => null]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/weather/current?city=Краснодар');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'city', 'temperature', 'description', 'humidity', 'is_mock'
            ])
            ->assertJson(['is_mock' => true]);
    }

    public function test_weather_api_with_valid_key()
    {
        config(['services.openweathermap.key' => 'test-key']);

        Http::fake([
            'api.openweathermap.org/*' => Http::response([
                'name' => 'Krasnodar',
                'sys' => ['country' => 'RU'],
                'main' => [
                    'temp' => 20,
                    'feels_like' => 22,
                    'humidity' => 65,
                    'pressure' => 1013,
                ],
                'weather' => [[
                    'description' => 'clear sky',
                    'icon' => '01d',
                ]],
                'wind' => ['speed' => 3.2],
                'visibility' => 10000,
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/weather/current?city=Краснодар');

        $response->assertStatus(200)
            ->assertJsonFragment(['city' => 'Krasnodar'])
            ->assertJsonMissing(['is_mock' => true]);
    }

    public function test_weather_forecast_api()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/weather/forecast?city=Краснодар');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'forecast' => [
                    '*' => ['time', 'temperature', 'description', 'icon']
                ]
            ]);
    }
}