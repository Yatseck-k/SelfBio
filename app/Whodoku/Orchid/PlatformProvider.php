<?php

declare(strict_types=1);

namespace Whodoku\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);

        // ...
    }

    /**
     * Register the application menu.
     *
     * @return Menu[]
     */
    public function menu(): array
    {
        return [
            Menu::make('Блог')
                ->icon('bs.journal-text')
                ->route('platform.selfbio.blog')
                ->permission('platform.selfbio.blog.view')
                ->title('SelfBio'),

            Menu::make('Контакты')
                ->icon('bs.person-lines-fill')
                ->route('platform.selfbio.contacts')
                ->permission('platform.selfbio.contacts.view'),

            Menu::make('Главная страница')
                ->icon('bs.house')
                ->route('platform.selfbio.welcome')
                ->permission('platform.selfbio.welcome.view'),

            Menu::make('Обзор')
                ->icon('bs.book')
                ->title('Whodoku')
                ->route(config('platform.index')),

            Menu::make('Дела')
                ->icon('bs.collection')
                ->route('platform.puzzles')
                ->permission('platform.puzzles.view')
                ->title('Дела'),

            Menu::make('Сгенерировать дело')
                ->icon('bs.dice-5')
                ->route('platform.puzzles.generate')
                ->permission('platform.puzzles.generate'),

            Menu::make('Каталог предметов')
                ->icon('bs.lamp')
                ->route('platform.objects')
                ->permission('platform.puzzles.objects'),

            Menu::make('Оформление')
                ->icon('bs.palette')
                ->route('platform.appearance')
                ->permission('platform.puzzles.objects'),

            Menu::make('Выпуск дел')
                ->icon('bs.calendar-event')
                ->route('platform.release')
                ->permission('platform.puzzles.generate'),

            Menu::make('Игроки')
                ->icon('bs.person-badge')
                ->route('platform.players')
                ->permission('platform.puzzles.players'),

            Menu::make('Заказы')
                ->icon('bs.cart')
                ->route('platform.orders')
                ->permission('platform.puzzles.players'),

            Menu::make('Оценка сложности')
                ->icon('bs.star-half')
                ->route('platform.difficulty')
                ->permission('platform.puzzles.generate'),

            Menu::make('Навыки')
                ->icon('bs.stars')
                ->route('platform.skills')
                ->permission('platform.puzzles.players'),

            Menu::make(__('Users'))
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title(__('Access Controls')),

            Menu::make(__('Roles'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles'),
        ];
    }

    /**
     * Register permissions for the application.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group('SelfBio')
                ->addPermission('platform.selfbio.blog.view', 'Просмотр записей блога')
                ->addPermission('platform.selfbio.blog.edit', 'Редактирование записей блога')
                ->addPermission('platform.selfbio.contacts.view', 'Просмотр контактов')
                ->addPermission('platform.selfbio.contacts.edit', 'Редактирование контактов')
                ->addPermission('platform.selfbio.welcome.view', 'Просмотр главной страницы')
                ->addPermission('platform.selfbio.welcome.edit', 'Редактирование главной страницы'),

            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles'))
                ->addPermission('platform.systems.users', __('Users')),

            ItemPermission::group('Дела')
                ->addPermission('platform.puzzles.view', 'Просмотр дел')
                ->addPermission('platform.puzzles.edit', 'Редактирование карты')
                ->addPermission('platform.puzzles.validate', 'Проверка дела')
                ->addPermission('platform.puzzles.publish', 'Публикация дел')
                ->addPermission('platform.puzzles.delete', 'Удаление дел')
                ->addPermission('platform.puzzles.generate', 'Генерация дел')
                ->addPermission('platform.puzzles.objects', 'Каталог предметов и оформление')
                ->addPermission('platform.puzzles.players', 'Игроки'),
        ];
    }
}
