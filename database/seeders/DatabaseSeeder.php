<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ContactInfo;
use App\Models\BlogPost;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Создаем контактную информацию
        ContactInfo::create([
            'name' => 'Иван Иванов',
            'phone' => '+7 (999) 123-45-67',
            'email' => 'ivan@example.com',
            'telegram' => '@ivan_dev',
            'github' => 'ivan-dev',
            'socials' => [
                ['type' => 'LinkedIn', 'url' => 'https://linkedin.com/in/ivan-dev'],
                ['type' => 'Twitter', 'url' => 'https://twitter.com/ivan_dev'],
            ]
        ]);

        // Создаем тестовые посты блога
        BlogPost::create([
            'title' => 'Добро пожаловать в мой блог!',
            'slug' => 'welcome-to-blog',
            'preview' => 'Это мой первый пост в блоге. Здесь я буду делиться интересными мыслями о программировании и технологиях.',
            'body' => '<p>Добро пожаловать в мой персональный блог!</p><p>Здесь я буду делиться:</p><ul><li>Опытом в разработке</li><li>Новыми технологиями</li><li>Интересными решениями</li></ul><p>Следите за обновлениями!</p>',
            'published_at' => now()->subDays(1),
        ]);

        BlogPost::create([
            'title' => 'Laravel и Vue.js - мощная комбинация',
            'slug' => 'laravel-vue-combination',
            'preview' => 'Рассказываю о том, почему Laravel в связке с Vue.js - отличный выбор для современных web-приложений.',
            'body' => '<p>Laravel и Vue.js представляют собой отличную комбинацию для создания современных web-приложений.</p><h3>Преимущества Laravel:</h3><ul><li>Элегантный синтаксис</li><li>Мощная ORM Eloquent</li><li>Встроенная авторизация</li></ul><h3>Преимущества Vue.js:</h3><ul><li>Реактивность</li><li>Простота изучения</li><li>Отличная документация</li></ul>',
            'published_at' => now()->subHours(12),
        ]);

        BlogPost::create([
            'title' => 'Будущее веб-разработки в 2025',
            'slug' => 'web-development-future-2025',
            'preview' => 'Мои мысли о том, куда движется веб-разработка и какие технологии стоит изучать в 2025 году.',
            'body' => '<p>2025 год обещает быть интересным для веб-разработки.</p><h3>Тренды 2025:</h3><ul><li>AI-интеграция</li><li>WebAssembly</li><li>Serverless архитектура</li><li>JAMstack</li></ul><p>Важно оставаться в курсе последних тенденций!</p>',
            'published_at' => now()->subHours(6),
        ]);
    }
}
