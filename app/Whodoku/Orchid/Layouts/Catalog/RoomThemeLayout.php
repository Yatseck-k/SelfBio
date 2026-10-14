<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Layouts\Catalog;

use Illuminate\Support\HtmlString;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Whodoku\Models\ObjectKind;
use Whodoku\Models\RoomTheme;

class RoomThemeLayout extends Table
{
    /** @var string */
    #[\Override]
    public $target = 'themes';

    /** @return TD[] */
    public function columns(): array
    {
        $labels = ObjectKind::query()->pluck('label', 'code');

        return [
            TD::make('name', 'Комната')->cantHide(),
            TD::make('kinds', 'Мебель в комнате')->render(fn (RoomTheme $theme) => implode(', ', array_map(fn ($code) => $labels[$code] ?? $code, $theme->kinds))),
            TD::make('active', 'Статус')->render(fn (RoomTheme $theme) => new HtmlString($theme->active ? '<span class="badge bg-success">включена</span>' : '<span class="badge bg-secondary">отключена</span>')),
            TD::make('actions', '')->align(TD::ALIGN_RIGHT)->cantHide()->width('4rem')->render(fn (RoomTheme $theme) => DropDown::make()->icon('bs.three-dots-vertical')->list([
                ModalToggle::make('Изменить')->modalTitle("Комната «{$theme->name}»")->asyncParameters(['theme' => $theme->id])->modal('themeModal')->method('saveTheme')->icon('bs.pencil'),
                Button::make($theme->active ? 'Отключить' : 'Включить')->icon($theme->active ? 'bs.eye-slash' : 'bs.eye')
                    ->method('toggleTheme', ['theme' => $theme->id]),
                Button::make('Удалить')->icon('bs.trash3')->type(Color::DANGER)
                    ->confirm("Удалить комнату «{$theme->name}»?")->method('deleteTheme', ['theme' => $theme->id]),
            ])),
        ];
    }
}
