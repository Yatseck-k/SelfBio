<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Catalog;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\ModalToggle;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Puzzle\ObjectCatalogException;
use Whodoku\Application\Puzzle\ObjectCatalogService;
use Whodoku\Models\ObjectKind;
use Whodoku\Models\ObjectName;
use Whodoku\Models\RoomTheme;
use Whodoku\Orchid\Layouts\Catalog\ObjectKindLayout;
use Whodoku\Orchid\Layouts\Catalog\ObjectNameLayout;
use Whodoku\Orchid\Layouts\Catalog\RoomThemeLayout;

class ObjectCatalogScreen extends Screen
{
    public function __construct(private readonly ObjectCatalogService $catalog) {}

    /** @return array<string, mixed> */
    public function query(): iterable
    {
        $tab = $this->tab();
        $kinds = collect();
        if ($tab === 'kinds') {
            $usage = $this->catalog->usage();
            $kinds = ObjectKind::query()->withCount('names')->orderByDesc('occupiable')->orderBy('label')->get();
            foreach ($kinds as $kind) {
                $kind->usage = $usage[$kind->code] ?? 0;
            }
        }

        // Only the list on the open tab is loaded and drawn (see tabs.blade.php).
        return [
            'tab' => $tab,
            'kinds' => $kinds,
            'names' => $tab === 'names' ? ObjectName::query()->with('kind')->orderBy('object_kind_id')->orderBy('name')->get() : collect(),
            'themes' => $tab === 'themes' ? RoomTheme::query()->orderBy('name')->get() : collect(),
            'problems' => $this->catalog->problems(),
        ];
    }

    private function tab(): string
    {
        $tab = request()->query('tab');

        return in_array($tab, ['names', 'themes'], true) ? $tab : 'kinds';
    }

    public function name(): ?string
    {
        return 'Каталог предметов';
    }

    public function description(): ?string
    {
        return 'Виды мебели, их названия в показаниях и мебель комнат. Генератор берёт то, что включено.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.objects'];
    }

    /** @return Action[] */
    public function commandBar(): iterable
    {
        return [
            ModalToggle::make('Добавить вид')->modalTitle('Новый вид предмета')->modal('kindModal')->method('saveKind')->icon('bs.plus-circle'),
            ModalToggle::make('Добавить название')->modalTitle('Новое название предмета')->modal('nameModal')->method('saveName')->icon('bs.plus-circle'),
            ModalToggle::make('Добавить комнату')->modalTitle('Новая комната')->modal('themeModal')->method('saveTheme')->icon('bs.plus-circle'),
        ];
    }

    public function layout(): iterable
    {
        return [
            Layout::view('orchid.catalog.notes'),
            Layout::view('orchid.catalog.tabs'),
            match ($this->tab()) {
                'names' => new ObjectNameLayout,
                'themes' => new RoomThemeLayout,
                default => new ObjectKindLayout,
            },

            Layout::modal('kindModal', Layout::rows([
                Input::make('kind.code')->title('Код')->placeholder('armchair')->help('Латиница, цифры и «_». Задаётся при создании и не меняется.'),
                Input::make('kind.label')->title('Название вида')->required(),
                Input::make('kind.icon')->title('Значок')->help('Один эмодзи, он показывается на карте.')->required(),
                CheckBox::make('kind.occupiable')->title('Используемый')->sendTrueOrFalse()->placeholder('Персонажа можно разместить на предмете')
                    ->help('У вида, который уже стоит на картах, признак менять нельзя.'),
                Input::make('kind.standing_phrase')->title('Как говорят в показании')->placeholder('на диване')->help('Нужно для подлога: «кто находился на диване». Можно оставить пустым.'),
            ]))->async('asyncKind')->applyButton('Сохранить')->closeButton('Отмена'),

            Layout::modal('nameModal', Layout::rows([
                Select::make('name.object_kind_id')->title('Вид')->fromModel(ObjectKind::class, 'label')->required(),
                Input::make('name.name')->title('Название')->placeholder('барный табурет')->required(),
            ]))->async('asyncName')->applyButton('Сохранить')->closeButton('Отмена'),

            Layout::modal('themeModal', Layout::rows([
                Input::make('theme.name')->title('Комната')->placeholder('библиотека')->required(),
                Select::make('theme.kinds')->title('Мебель комнаты')->fromModel(ObjectKind::class, 'label', 'code')->multiple()->required(),
            ]))->async('asyncTheme')->applyButton('Сохранить')->closeButton('Отмена'),
        ];
    }

    /** @return array<string, mixed> */
    public function asyncKind(?ObjectKind $kind = null): array
    {
        return ['kind' => $kind?->exists ? $kind : new ObjectKind(['occupiable' => false])];
    }

    /** @return array<string, mixed> */
    public function asyncName(?ObjectName $name = null): array
    {
        return ['name' => $name?->exists ? $name : new ObjectName];
    }

    /** @return array<string, mixed> */
    public function asyncTheme(?RoomTheme $theme = null): array
    {
        return ['theme' => $theme?->exists ? $theme : new RoomTheme(['kinds' => []])];
    }

    public function saveKind(Request $request, ObjectKind $kind): RedirectResponse
    {
        $data = $request->validate([
            'kind.code' => ['nullable', 'string', 'max:32'],
            'kind.label' => ['required', 'string', 'max:255'],
            'kind.icon' => ['required', 'string', 'max:16'],
            'kind.occupiable' => ['nullable', 'boolean'],
            'kind.standing_phrase' => ['nullable', 'string', 'max:255'],
        ])['kind'];

        return $this->attempt(fn () => $this->catalog->saveKind($kind, $data, $request->user()), 'Вид сохранён.');
    }

    public function toggleKind(Request $request, ObjectKind $kind): RedirectResponse
    {
        return $this->attempt(fn () => $this->catalog->setKindActive($kind, !$kind->active, $request->user()), 'Готово.');
    }

    public function deleteKind(Request $request, ObjectKind $kind): RedirectResponse
    {
        return $this->attempt(fn () => $this->catalog->deleteKind($kind, $request->user()), 'Вид удалён.');
    }

    public function saveName(Request $request, ObjectName $name): RedirectResponse
    {
        $data = $request->validate([
            'name.object_kind_id' => ['required', 'integer'],
            'name.name' => ['required', 'string', 'max:255'],
        ])['name'];

        return $this->attempt(fn () => $this->catalog->saveName($name, (int) $data['object_kind_id'], $data['name'], $request->user()), 'Название сохранено.');
    }

    public function toggleName(Request $request, ObjectName $name): RedirectResponse
    {
        return $this->attempt(fn () => $this->catalog->setNameActive($name, !$name->active, $request->user()), 'Готово.');
    }

    public function deleteName(Request $request, ObjectName $name): RedirectResponse
    {
        return $this->attempt(fn () => $this->catalog->deleteName($name, $request->user()), 'Название удалено.');
    }

    public function saveTheme(Request $request, RoomTheme $theme): RedirectResponse
    {
        $data = $request->validate([
            'theme.name' => ['required', 'string', 'max:255'],
            'theme.kinds' => ['required', 'array', 'min:1'],
            'theme.kinds.*' => ['string'],
        ])['theme'];

        return $this->attempt(fn () => $this->catalog->saveTheme($theme, $data['name'], array_values($data['kinds']), $request->user()), 'Комната сохранена.');
    }

    public function toggleTheme(Request $request, RoomTheme $theme): RedirectResponse
    {
        return $this->attempt(fn () => $this->catalog->setThemeActive($theme, !$theme->active, $request->user()), 'Готово.');
    }

    public function deleteTheme(Request $request, RoomTheme $theme): RedirectResponse
    {
        return $this->attempt(fn () => $this->catalog->deleteTheme($theme, $request->user()), 'Комната удалена.');
    }

    /** @param Closure(): mixed $change */
    private function attempt(Closure $change, string $success): RedirectResponse
    {
        try {
            $change();
            Toast::success($success);
        } catch (ObjectCatalogException $exception) {
            Toast::error($exception->getMessage());
        }

        return to_route('platform.objects', array_filter(['tab' => request()->query('tab')]));
    }
}
