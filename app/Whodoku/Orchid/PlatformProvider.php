<?php

declare(strict_types=1);

namespace Whodoku\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;
use Whodoku\Models\Incident;

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
        $user = auth()->user();
        // A group is shown when the person may open at least one of its items.
        $canAny = fn (array $permissions): bool => $user?->hasAnyAccess($permissions) ?? false;

        return [
            Menu::make('Настройки сайта')
                ->icon('bs.gear')
                ->route('platform.selfbio.settings')
                ->permission('platform.selfbio.settings.view'),

            Menu::make('Сайт-визитка')
                ->icon('bs.card-text')
                ->canSee($canAny(['platform.selfbio.settings.view', 'platform.selfbio.contacts.view', 'platform.selfbio.blog.view']))
                ->list([
                    Menu::make('Главная и «Обо мне»')->icon('bs.person-vcard')->route('platform.selfbio.profile')->permission('platform.selfbio.settings.view'),
                    Menu::make('Настройки сайта-визитки')->icon('bs.sliders')->route('platform.selfbio.card')->permission('platform.selfbio.settings.view'),
                    Menu::make('Контакты')->icon('bs.person-lines-fill')->route('platform.selfbio.contacts')->permission('platform.selfbio.contacts.view'),
                    Menu::make('Блог')->icon('bs.journal-text')->route('platform.selfbio.blog')->permission('platform.selfbio.blog.view'),
                ]),

            Menu::make('Игра Whodoku')
                ->icon('bs.controller')
                ->list([
                    Menu::make('Обзор')->icon('bs.book')->route(config('platform.index')),
                    Menu::make('Дела')->icon('bs.collection')->route('platform.puzzles')->permission('platform.puzzles.view'),
                    Menu::make('Сгенерировать дело')->icon('bs.dice-5')->route('platform.puzzles.generate')->permission('platform.puzzles.generate'),
                    Menu::make('Каталог предметов')->icon('bs.lamp')->route('platform.objects')->permission('platform.puzzles.objects'),
                    Menu::make('Оформление')->icon('bs.palette')->route('platform.appearance')->permission('platform.puzzles.objects'),
                    Menu::make('Выпуск дел')->icon('bs.calendar-event')->route('platform.release')->permission('platform.puzzles.generate'),
                    Menu::make('Игроки')->icon('bs.person-badge')->route('platform.players')->permission('platform.puzzles.players'),
                    Menu::make('Обращения игроков')->icon('bs.flag')->route('platform.incidents')->permission('platform.puzzles.validate')
                        ->badge(fn () => ($count = Incident::query()->where('status', Incident::NEW)->count()) > 0 ? (string) $count : null),
                    Menu::make('Заказы')->icon('bs.cart')->route('platform.orders')->permission('platform.puzzles.players'),
                    Menu::make('Оценка сложности')->icon('bs.star-half')->route('platform.difficulty')->permission('platform.puzzles.generate'),
                    Menu::make('Навыки')->icon('bs.stars')->route('platform.skills')->permission('platform.puzzles.players'),
                    Menu::make('Настройки игры')->icon('bs.gear-wide-connected')->route('platform.selfbio.game')->permission('platform.selfbio.settings.view'),
                ]),

            Menu::make('Личный кабинет')
                ->icon('bs.person-badge-fill')
                ->canSee($canAny(['platform.selfbio.account.view']))
                ->list([
                    Menu::make('Пользователи')->icon('bs.person-check')->route('platform.account.users')->permission('platform.selfbio.account.view'),
                    Menu::make('Ремонт')->icon('bs.hammer')->route('platform.account.repair')->permission('platform.selfbio.account.view'),
                ]),

            Menu::make('Доступ к админке')
                ->icon('bs.shield-lock')
                ->canSee($canAny(['platform.systems.users', 'platform.systems.roles']))
                ->list([
                    Menu::make(__('Users'))->icon('bs.people')->route('platform.systems.users')->permission('platform.systems.users'),
                    Menu::make(__('Roles'))->icon('bs.shield')->route('platform.systems.roles')->permission('platform.systems.roles'),
                ]),
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
            ItemPermission::group(config('app.name'))
                ->addPermission('platform.selfbio.blog.view', 'Просмотр записей блога')
                ->addPermission('platform.selfbio.blog.edit', 'Редактирование записей блога')
                ->addPermission('platform.selfbio.contacts.view', 'Просмотр контактов')
                ->addPermission('platform.selfbio.contacts.edit', 'Редактирование контактов')
                ->addPermission('platform.selfbio.settings.view', 'Просмотр настроек и текстов страниц')
                ->addPermission('platform.selfbio.settings.edit', 'Изменение названия сайта и текстов страниц')
                ->addPermission('platform.selfbio.account.view', 'Личный кабинет: просмотр пользователей и ремонта')
                ->addPermission('platform.selfbio.account.edit', 'Личный кабинет: управление пользователями'),

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
