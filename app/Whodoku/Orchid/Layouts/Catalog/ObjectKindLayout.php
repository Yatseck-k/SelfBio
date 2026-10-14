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
use Whodoku\Application\Appearance\AppearanceService;
use Whodoku\Models\ObjectKind;

class ObjectKindLayout extends Table
{
    /** @var string */
    #[\Override]
    public $target = 'kinds';

    /** @return TD[] */
    public function columns(): array
    {
        $images = [];
        foreach (resolve(AppearanceService::class)->slots()['kind'] as $slot) {
            $images[$slot['slot']] = $slot['url'];
        }

        return [
            TD::make('icon', 'Значок')->cantHide()->width('5rem')->render(function (ObjectKind $kind) use ($images) {
                $url = $images["kind:{$kind->code}"] ?? null;

                return new HtmlString($url === null ? e($kind->icon) : '<img src="'.e($url).'" alt="" width="32" height="32" style="object-fit:contain">');
            }),
            TD::make('label', 'Вид')->cantHide()->render(fn (ObjectKind $kind) => new HtmlString('<strong class="text-nowrap">'.e($kind->label).'</strong>')),
            TD::make('code', 'Код')->render(fn (ObjectKind $kind) => new HtmlString('<code class="text-nowrap">'.e($kind->code).'</code>')),
            TD::make('occupiable', 'Персонажа')->render(fn (ObjectKind $kind) => new HtmlString($kind->occupiable ? '<span class="badge bg-success">можно разместить</span>' : '<span class="badge bg-secondary">нельзя</span>')),
            TD::make('standing_phrase', 'В показании')->width('9rem')->render(fn (ObjectKind $kind) => $kind->standing_phrase ?? '—'),
            TD::make('names', 'Названий')->align(TD::ALIGN_RIGHT)->render(fn (ObjectKind $kind) => (string) $kind->names_count),
            TD::make('usage', 'На картах')->align(TD::ALIGN_RIGHT)->render(fn (ObjectKind $kind) => (string) ($kind->usage ?? 0)),
            TD::make('active', 'Статус')->render(fn (ObjectKind $kind) => new HtmlString($kind->active ? '<span class="badge bg-success">включён</span>' : '<span class="badge bg-secondary">отключён</span>')),
            TD::make('actions', '')->align(TD::ALIGN_RIGHT)->cantHide()->width('4rem')->render(fn (ObjectKind $kind) => DropDown::make()->icon('bs.three-dots-vertical')->list([
                ModalToggle::make('Изменить')->modalTitle("Вид «{$kind->label}»")->asyncParameters(['kind' => $kind->id])->modal('kindModal')->method('saveKind')->icon('bs.pencil'),
                Button::make($kind->active ? 'Отключить' : 'Включить')->icon($kind->active ? 'bs.eye-slash' : 'bs.eye')
                    ->method('toggleKind', ['kind' => $kind->id]),
                Button::make('Удалить')->icon('bs.trash3')->type(Color::DANGER)
                    ->confirm("Удалить вид «{$kind->label}» вместе с его названиями?")->method('deleteKind', ['kind' => $kind->id]),
            ])),
        ];
    }
}
