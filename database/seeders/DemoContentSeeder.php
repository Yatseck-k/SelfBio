<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\ContactInfo;
use App\Models\Welcome;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (! ContactInfo::query()->exists()) {
            ContactInfo::query()->create([
                'name' => 'Команда SelfBio (демо)',
                'email' => 'hello@example.com',
                'socials' => [
                    ['type' => 'Демо-сайт', 'url' => 'https://example.com'],
                ],
            ]);
            Cache::forget('contact.show.data');
        }

        if (! Welcome::query()->exists()) {
            Welcome::query()->create([
                'title' => 'SelfBio: заметки и проекты',
                'body' => 'Это демонстрационная главная страница. Здесь можно найти контакты, прочитать записи блога, сыграть в Whodoku и войти в раздел ремонта.',
            ]);
            Cache::forget('welcome.index.data');
        }

        if (BlogPost::query()->exists()) {
            return;
        }

        foreach ([
            [
                'title' => 'Добро пожаловать в SelfBio',
                'slug' => 'demo-welcome-to-selfbio',
                'preview' => 'Короткий обзор разделов сайта и того, что здесь можно посмотреть.',
                'body' => '<p>Это демонстрационная запись блога SelfBio. Здесь появляются заметки о проектах, идеях и ходе работы.</p><h2>Что есть на сайте</h2><ul><li>Контакты и блог — на главной странице.</li><li>Whodoku — игра, которую можно открыть из верхнего меню.</li><li>Раздел ремонта — материалы для пользователей с доступом.</li></ul>',
                'published_at' => now()->subDays(7),
            ],
            [
                'title' => 'Как устроен Whodoku',
                'slug' => 'demo-whodoku',
                'preview' => 'Несколько слов об игровой части SelfBio и переходе к головоломкам.',
                'body' => '<p>Whodoku — игровая часть сайта с головоломками. Откройте пункт Whodoku в меню, чтобы перейти к каталогу и выбрать задачу.</p><p>Эта запись служит примером оформления материалов блога. Позже её можно заменить настоящей историей о разработке игры.</p>',
                'published_at' => now()->subDays(4),
            ],
            [
                'title' => 'Материалы ремонта в одном месте',
                'slug' => 'demo-renovation-materials',
                'preview' => 'Как найти записи, фотографии и файлы по ремонту после входа.',
                'body' => '<p>После входа в закрытый раздел ремонта можно просматривать материалы по комнатам, искать сообщения и открывать вложения.</p><p>Это демонстрационный текст. Для публикации замените его реальной заметкой в административной панели.</p>',
                'published_at' => now()->subDay(),
            ],
        ] as $post) {
            BlogPost::query()->create($post);
        }

        Cache::put('selfbio.blog.version', (string) now()->timestamp);
    }
}
