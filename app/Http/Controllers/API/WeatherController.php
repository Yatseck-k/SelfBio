<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherController extends Controller
{
    public function current(Request $request)
    {
        $city = $request->get('city', 'Краснодар');
        $apiKey = env('OPENWEATHERMAP_API_KEY');

        if (! $apiKey) {
            return $this->getMockWeatherData($city);
        }

        $cacheKey = "weather.{$city}";

        $weather = Cache::remember($cacheKey, 1800, function () use ($city, $apiKey) { // 30 минут TTL
            try {
                $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
                    'q' => $city,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang' => 'ru',
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'city' => $data['name'],
                        'country' => $data['sys']['country'],
                        'temperature' => round($data['main']['temp']),
                        'feels_like' => round($data['main']['feels_like']),
                        'humidity' => $data['main']['humidity'],
                        'pressure' => $data['main']['pressure'],
                        'description' => ucfirst((string) $data['weather'][0]['description']),
                        'icon' => $data['weather'][0]['icon'],
                        'wind_speed' => $data['wind']['speed'],
                        'visibility' => $data['visibility'] / 1000, // км
                        'updated_at' => now()->toISOString(),
                    ];
                }

                return $this->getMockWeatherData($city, false);
            } catch (\Exception $e) {
                Log::error('Weather API error: '.$e->getMessage());

                return $this->getMockWeatherData($city, false);
            }
        });

        return response()->json($weather);
    }

    public function forecast(Request $request)
    {
        $city = $request->get('city', 'Краснодар');
        $apiKey = env('OPENWEATHERMAP_API_KEY');

        if (! $apiKey) {
            return response()->json([
                'forecast' => $this->getMockForecastData(),
            ]);
        }

        $cacheKey = "weather.forecast.{$city}";

        $forecast = Cache::remember($cacheKey, 1800, function () use ($city, $apiKey) { // 30 минут TTL
            try {
                $response = Http::get('https://api.openweathermap.org/data/2.5/forecast', [
                    'q' => $city,
                    'appid' => $apiKey,
                    'units' => 'metric',
                    'lang' => 'ru',
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    $forecast = [];
                    foreach (array_slice($data['list'], 0, 5) as $item) {
                        $forecast[] = [
                            'time' => date('H:i', $item['dt']),
                            'temperature' => round($item['main']['temp']),
                            'description' => ucfirst((string) $item['weather'][0]['description']),
                            'icon' => $item['weather'][0]['icon'],
                        ];
                    }

                    return $forecast;
                }

                return $this->getMockForecastData();
            } catch (\Exception $e) {
                Log::error('Forecast API error: '.$e->getMessage());

                return $this->getMockForecastData();
            }
        });

        return response()->json(['forecast' => $forecast]);
    }

    private function getMockWeatherData($city, $cached = true): array
    {
        return [
            'city' => $city,
            'country' => 'RU',
            'temperature' => 18,
            'feels_like' => 20,
            'humidity' => 65,
            'pressure' => 1013,
            'description' => 'Переменная облачность',
            'icon' => '02d',
            'wind_speed' => 3.2,
            'visibility' => 10,
            'updated_at' => now()->toISOString(),
            'is_mock' => ! $cached,
        ];
    }

    private function getMockForecastData(): array
    {
        return [
            ['time' => '12:00', 'temperature' => 19, 'description' => 'Ясно', 'icon' => '01d'],
            ['time' => '15:00', 'temperature' => 22, 'description' => 'Переменная облачность', 'icon' => '02d'],
            ['time' => '18:00', 'temperature' => 20, 'description' => 'Облачно', 'icon' => '03d'],
            ['time' => '21:00', 'temperature' => 16, 'description' => 'Ясно', 'icon' => '01n'],
            ['time' => '00:00', 'temperature' => 14, 'description' => 'Ясно', 'icon' => '01n'],
        ];
    }
}
