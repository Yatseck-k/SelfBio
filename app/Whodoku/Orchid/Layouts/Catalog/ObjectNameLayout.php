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
use Whodoku\Models\ObjectName;

class ObjectNameLayout extends Table
{
    /** @var string */
    #[\Override]
    public $target = 'names';

    /** @return TD[] */
    public function columns(): array
    {
        return [
            TD::make('name', 'Название в показаниях')->cantHide(),
            TD::make('kind', 'Вид')->render(fn (ObjectName $name) => ($name->kind->icon ?? '').' '.($name->kind->label ?? '—').($name->kind->occupiable ? ' (используемый)' : ' (неиспользуемый)')),
            TD::make('active', 'Статус')->render(fn (ObjectName $name) => new HtmlString($name->active ? '<span class="badge bg-success">включено</span>' : '<span class="badge bg-secondary">отключено</span>')),
            TD::make('actions', '')->align(TD::ALIGN_RIGHT)->cantHide()->width('4rem')->render(fn (ObjectName $name) => DropDown::make()->icon('bs.three-dots-vertical')->list([
                ModalToggle::make('Изменить')->modalTitle("Название «{$name->name}»")->asyncParameters(['name' => $name->id])->modal('nameModal')->method('saveName')->icon('bs.pencil'),
                Button::make($name->active ? 'Отключить' : 'Включить')->icon($name->active ? 'bs.eye-slash' : 'bs.eye')
                    ->method('toggleName', ['name' => $name->id]),
                Button::make('Удалить')->icon('bs.trash3')->type(Color::DANGER)
                    ->confirm("Удалить название «{$name->name}»? Уже созданные карты его сохранят.")->method('deleteName', ['name' => $name->id]),
            ])),
        ];
    }
}
