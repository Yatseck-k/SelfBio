<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Puzzle;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Puzzle\ObjectCatalogService;
use Whodoku\Application\Puzzle\PuzzleMapUpdateException;
use Whodoku\Application\Puzzle\PuzzleMapUpdater;
use Whodoku\Models\Puzzle;

class PuzzleMapScreen extends Screen
{
    public function query(Puzzle $puzzle): iterable
    {
        return [
            'kinds' => (new ObjectCatalogService)->current()->forEditor(),
            'puzzle' => ['id' => $puzzle->id, 'slug' => $puzzle->slug, 'status' => $puzzle->status, 'rows' => $puzzle->rows, 'cols' => $puzzle->cols],
            'map' => [
                'blocked_cells' => $puzzle->blocked_cells ?? [],
                'windows' => $puzzle->windows ?? [],
                'rooms' => $puzzle->rooms()->get(['code', 'name', 'cells'])->map->only(['code', 'name', 'cells'])->all(),
                'walls' => $puzzle->walls()->get()->map(fn ($wall) => ['from' => [$wall->row_from, $wall->col_from], 'to' => [$wall->row_to, $wall->col_to]])->all(),
                'objects' => $puzzle->objects()->get()->map(fn ($object) => ['code' => $object->code, 'type' => $object->type, 'name' => $object->name, 'cell' => [$object->row, $object->col], 'blocking' => $object->blocking, 'occupiable' => $object->occupiable])->all(),
            ],
        ];
    }

    public function name(): ?string
    {
        return 'Редактор карты';
    }

    public function description(): ?string
    {
        return 'Изменение карты снимет опубликованное дело с публикации до повторной проверки.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.edit'];
    }

    /** @return Action[] */
    public function commandBar(): iterable
    {
        return [Button::make('Сохранить карту')->icon('bs.save')->method('save')];
    }

    public function layout(): iterable
    {
        return [Layout::view('orchid.puzzle.map-editor')];
    }

    public function save(Request $request, Puzzle $puzzle, PuzzleMapUpdater $updater): RedirectResponse
    {
        $map = json_decode((string) $request->input('map'), true);
        if (!is_array($map)) {
            return back()->withErrors(['map' => 'Карта передана в неверном формате.']);
        }
        try {
            $updater->update($puzzle, $map, $request->user());
        } catch (PuzzleMapUpdateException $exception) {
            return back()->withErrors(['map' => $exception->getMessage()]);
        }
        Toast::success('Карта сохранена. Дело переведено в черновик и требует проверки.');

        return to_route('platform.puzzles.show', $puzzle);
    }
}
