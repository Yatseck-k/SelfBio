<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateWeatherData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $cities = ['Краснодар']
    ) {}

    public function handle(): void
    {
        $apiKey = env('OPENWEATHERMAP_API_KEY');

        if (! $apiKey) {
            Log::warning('OpenWeatherMap API key not configured');

            return;
        }

        foreach ($this->cities as $city) {
            try {
                $this->updateCityWeather($city, $apiKey);

                // Небольшая пауза между запросами
                sleep(1);
            } catch (\Exception $e) {
                Log::error("Failed to update weather for {$city}: ".$e->getMessage());
            }
        }
    }

    private function updateCityWeather(string $city, string $apiKey): void
    {
        $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
            'q' => $city,
            'appid' => $apiKey,
            'units' => 'metric',
            'lang' => 'ru',
        ]);

        if ($response->successful()) {
            $data = $response->json();

            $weatherData = [
                'city' => $data['name'],
                'country' => $data['sys']['country'],
                'temperature' => round($data['main']['temp']),
                'feels_like' => round($data['main']['feels_like']),
                'humidity' => $data['main']['humidity'],
                'pressure' => $data['main']['pressure'],
                'description' => ucfirst((string) $data['weather'][0]['description']),
                'icon' => $data['weather'][0]['icon'],
                'wind_speed' => $data['wind']['speed'],
                'visibility' => $data['visibility'] / 1000,
                'updated_at' => now()->toISOString(),
            ];

            // Кэшируем на 30 минут
            Cache::put("weather.{$city}", $weatherData, 1800);

            Log::info("Weather data updated for {$city}");
        } else {
            throw new \Exception('API request failed with status: '.$response->status());
        }
    }
}
