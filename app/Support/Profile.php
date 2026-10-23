<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

final class Profile
{
    private const string KEY_PREFIX = 'profile.';

    /** @return array<string, mixed> */
    public static function content(string $locale = 'ru'): array
    {
        $data = self::editor($locale);
        $data['about'] = array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', $data['about_text']) ?: [])));
        $data['hero_tags'] = self::items((string) $data['hero_tags']);
        $data['stack'] = array_map(fn (array $group): array => [
            'title' => $group['title'],
            'items' => self::items($group['items']),
        ], $data['stack']);

        return $data;
    }

    /** @return array<string, mixed> */
    public static function editor(string $locale = 'ru'): array
    {
        $locale = $locale === 'en' ? 'en' : 'ru';
        $saved = rescue(
            fn (): array => Cache::remember(self::KEY_PREFIX.$locale, 3600, function () use ($locale): array {
                $json = SiteSetting::query()->where('key', self::KEY_PREFIX.$locale)->value('value');
                $value = json_decode((string) $json, true);

                return is_array($value) ? $value : [];
            }),
            [],
            false,
        );

        return array_replace(self::defaults($locale), is_array($saved) ? $saved : []);
    }

    /** @param array<string, mixed> $data */
    public static function save(string $locale, array $data): void
    {
        $locale = $locale === 'en' ? 'en' : 'ru';
        SiteSetting::query()->updateOrCreate(
            ['key' => self::KEY_PREFIX.$locale],
            ['value' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
        );
        Cache::forget(self::KEY_PREFIX.$locale);
    }

    public static function reset(string $locale): void
    {
        $locale = $locale === 'en' ? 'en' : 'ru';
        SiteSetting::query()->where('key', self::KEY_PREFIX.$locale)->delete();
        Cache::forget(self::KEY_PREFIX.$locale);
    }

    public static function path(string $page, string $locale = 'ru'): string
    {
        $suffix = match ($page) {
            'about' => '/about',
            'contacts' => '/contacts',
            default => '',
        };

        return ($locale === 'en' ? '/en' : '').$suffix ?: '/';
    }

    /** @return list<string> */
    private static function items(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /** @return array<string, mixed> */
    private static function defaults(string $locale): array
    {
        return $locale === 'en' ? self::english() : self::russian();
    }

    /** @return array<string, mixed> */
    private static function russian(): array
    {
        return [
            'name' => 'Антон Яценко',
            'role' => 'Backend Software Engineer',
            'lead' => 'Разрабатываю серверные приложения, API и интеграции. Основной фокус — PHP, архитектура приложений и работа с данными.',
            'hero_eyebrow' => 'Персональный сайт',
            'hero_tags' => 'PHP, Laravel, Symfony, API',
            'about_title' => 'Обо мне',
            'about_eyebrow' => 'Профессиональный профиль',
            'about_lead' => 'Разрабатываю серверные приложения, API и интеграции. Основной фокус — PHP, архитектура приложений и работа с данными.',
            'about_button' => 'Обо мне',
            'contact_button' => 'Связаться',
            'about_text' => "Я занимаюсь backend-разработкой: проектирую приложения, API и интеграции, работаю с базами данных и фоновыми задачами.\n\nВ основе моего стека — PHP, Laravel и Symfony. Также использую Python и инструменты инфраструктуры там, где они помогают решить задачу.\n\nВ работе уделяю внимание понятной структуре кода, тестам, производительности и поддерживаемости.",
            'focus_enabled' => true,
            'focus_order' => 1,
            'focus_eyebrow' => 'Компетенции',
            'focus_title' => 'Направления работы',
            'focus' => [
                ['title' => 'Архитектура backend', 'text' => 'Структура приложений, разделение ответственности и поддерживаемый код.'],
                ['title' => 'API и интеграции', 'text' => 'Контракты, обмен данными и взаимодействие сервисов.'],
                ['title' => 'Данные и производительность', 'text' => 'PostgreSQL, MySQL, Redis и работа с запросами.'],
                ['title' => 'Инфраструктура', 'text' => 'Docker, Linux и процессы доставки приложений.'],
            ],
            'stack_enabled' => true,
            'stack_order' => 2,
            'stack_eyebrow' => 'Технологии',
            'stack_title' => 'Инструменты и технологии',
            'stack' => [
                ['title' => 'Основное', 'items' => 'PHP, Laravel, Symfony, PostgreSQL, MySQL, Redis'],
                ['title' => 'Дополнительно', 'items' => 'Python, FastAPI, RabbitMQ, Docker, Linux, Vue.js'],
                ['title' => 'Качество', 'items' => 'PHPUnit, Pest, PHPStan, Rector, Git'],
            ],
            'writing_enabled' => true,
            'writing_order' => 3,
            'writing_eyebrow' => 'Статьи',
            'writing_title' => 'Последние статьи',
            'articles_button' => 'Все статьи',
            'contact_enabled' => true,
            'contact_order' => 4,
            'contact_eyebrow' => 'Связаться',
            'contact_title' => 'Давайте обсудим задачу',
            'contact_text' => 'Со мной можно связаться по почте или через профессиональные профили.',
        ];
    }

    /** @return array<string, mixed> */
    private static function english(): array
    {
        return [
            'name' => 'Anton Yatsenko',
            'role' => 'Backend Software Engineer',
            'lead' => 'I build backend applications, APIs and integrations. My main focus is PHP, application architecture and data.',
            'hero_eyebrow' => 'Personal website',
            'hero_tags' => 'PHP, Laravel, Symfony, API',
            'about_title' => 'About me',
            'about_eyebrow' => 'Professional profile',
            'about_lead' => 'I build backend applications, APIs and integrations. My main focus is PHP, application architecture and data.',
            'about_button' => 'About me',
            'contact_button' => 'Get in touch',
            'about_text' => "I work in backend development, designing applications, APIs and integrations and working with databases and background jobs.\n\nMy core stack is PHP, Laravel and Symfony. I also use Python and infrastructure tools where they fit the problem.\n\nI care about clear code structure, tests, performance and maintainability.",
            'focus_enabled' => true,
            'focus_order' => 1,
            'focus_eyebrow' => 'Expertise',
            'focus_title' => 'What I work on',
            'focus' => [
                ['title' => 'Backend architecture', 'text' => 'Application structure, separation of responsibilities and maintainable code.'],
                ['title' => 'APIs and integrations', 'text' => 'Contracts, data exchange and communication between services.'],
                ['title' => 'Data and performance', 'text' => 'PostgreSQL, MySQL, Redis and query optimisation.'],
                ['title' => 'Infrastructure', 'text' => 'Docker, Linux and application delivery.'],
            ],
            'stack_enabled' => true,
            'stack_order' => 2,
            'stack_eyebrow' => 'Technology',
            'stack_title' => 'Tools and technologies',
            'stack' => [
                ['title' => 'Core', 'items' => 'PHP, Laravel, Symfony, PostgreSQL, MySQL, Redis'],
                ['title' => 'Additional', 'items' => 'Python, FastAPI, RabbitMQ, Docker, Linux, Vue.js'],
                ['title' => 'Quality', 'items' => 'PHPUnit, Pest, PHPStan, Rector, Git'],
            ],
            'writing_enabled' => true,
            'writing_order' => 3,
            'writing_eyebrow' => 'Writing',
            'writing_title' => 'Recent articles',
            'articles_button' => 'All articles',
            'contact_enabled' => true,
            'contact_order' => 4,
            'contact_eyebrow' => 'Get in touch',
            'contact_title' => "Let's talk",
            'contact_text' => 'You can reach me by email or through my professional profiles.',
        ];
    }
}
