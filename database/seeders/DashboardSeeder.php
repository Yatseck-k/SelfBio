<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\ContactInfo;
use App\Models\User;
use Illuminate\Database\Seeder;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        // Create test user if not exists
        $user = User::firstOrCreate(
            ['email' => 'hello@yats.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('TheBigPassword'),
            ]
        );

        // Create contact info
        ContactInfo::firstOrCreate([], [
            'name' => 'Backend Developer',
            'phone' => '+7 (999) 123-45-67',
            'email' => 'developer@example.com',
            'telegram' => '@developer',
            'github' => 'developer',
            'socials' => [
                ['type' => 'linkedin', 'url' => 'https://linkedin.com/in/developer'],
                ['type' => 'twitter', 'url' => 'https://twitter.com/developer'],
            ],
        ]);

        // Create sample blog posts
        $posts = [
            [
                'title' => 'Добро пожаловать в мой блог',
                'slug' => 'welcome-to-my-blog',
                'preview' => 'Первый пост в моем техническом блоге о разработке.',
                'body' => '<h2>Добро пожаловать!</h2><p>Это мой первый пост в блоге. Здесь я буду делиться опытом разработки, интересными решениями и полезными материалами.</p><p>Следите за обновлениями!</p>',
                'published_at' => now(),
            ],
            [
                'title' => 'Laravel + Vue.js: Современный стек разработки',
                'slug' => 'laravel-vue-modern-stack',
                'preview' => 'Обзор современного стека для веб-разработки с Laravel и Vue.js.',
                'body' => '<h2>Почему Laravel + Vue.js?</h2><p>Этот стек позволяет создавать мощные и современные веб-приложения.</p><h3>Преимущества:</h3><ul><li>Быстрая разработка</li><li>Отличная производительность</li><li>Богатая экосистема</li></ul>',
                'published_at' => now()->subDays(3),
            ],
        ];

        foreach ($posts as $postData) {
            BlogPost::firstOrCreate(
                ['slug' => $postData['slug']],
                $postData
            );
        }

        // Create sample tasks for the user
        $user->tasks()->firstOrCreate(
            ['title' => 'Завершить дашборд'],
            [
                'description' => 'Доработать все виджеты и интеграции',
                'priority' => 'high',
                'due_date' => now()->addDays(2),
            ]
        );

        $user->tasks()->firstOrCreate(
            ['title' => 'Написать документацию'],
            [
                'description' => 'Создать README и техническую документацию',
                'priority' => 'medium',
                'due_date' => now()->addWeek(),
            ]
        );

        // Create sample calendar events
        $user->calendarEvents()->firstOrCreate(
            ['title' => 'Встреча с командой'],
            [
                'description' => 'Еженедельная встреча команды разработки',
                'start_date' => now()->addDays(1)->setTime(10, 0),
                'end_date' => now()->addDays(1)->setTime(11, 0),
                'color' => '#3B82F6',
            ]
        );

        $user->calendarEvents()->firstOrCreate(
            ['title' => 'Код-ревью'],
            [
                'description' => 'Ревью нового функционала',
                'start_date' => now()->addDays(2)->setTime(14, 0),
                'end_date' => now()->addDays(2)->setTime(15, 30),
                'color' => '#10B981',
            ]
        );

        // Create sample quick links
        $links = [
            [
                'title' => 'GitHub',
                'url' => 'https://github.com',
                'icon' => '🐙',
                'category' => 'Разработка',
                'color' => '#333333',
                'order' => 1,
            ],
            [
                'title' => 'Laravel Docs',
                'url' => 'https://laravel.com/docs',
                'icon' => '📚',
                'category' => 'Разработка',
                'color' => '#FF2D20',
                'order' => 2,
            ],
            [
                'title' => 'Vue.js',
                'url' => 'https://vuejs.org',
                'icon' => '💚',
                'category' => 'Разработка',
                'color' => '#4FC08D',
                'order' => 3,
            ],
            [
                'title' => 'Gmail',
                'url' => 'https://gmail.com',
                'icon' => '📧',
                'category' => 'Общее',
                'color' => '#EA4335',
                'order' => 4,
            ],
        ];

        foreach ($links as $linkData) {
            $user->quickLinks()->firstOrCreate(
                ['title' => $linkData['title']],
                array_merge($linkData, ['user_id' => $user->id])
            );
        }

        $this->command->info('Dashboard demo data created successfully!');
    }
}
