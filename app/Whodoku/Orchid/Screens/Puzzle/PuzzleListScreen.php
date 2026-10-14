<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Puzzle;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Puzzle\PuzzleDeleter;
use Whodoku\Models\Puzzle;
use Whodoku\Orchid\Layouts\Puzzle\PuzzleListLayout;

class PuzzleListScreen extends Screen
{
    /**
     * @return array<string, mixed>
     */
    public function query(): iterable
    {
        // Orchid column sorting sends `sort=column` (ascending) or `sort=-column`; newest cases come first by default.
        $sort = (string) request()->query('sort', '-created_at');
        $column = ltrim($sort, '-');
        if (!in_array($column, ['created_at', 'title', 'status', 'revision', 'published_at'], true)) {
            $column = 'created_at';
            $sort = '-created_at';
        }

        return [
            'puzzles' => Puzzle::query()
                ->select(['id', 'slug', 'title', 'status', 'rows', 'cols', 'revision', 'published_at', 'created_at'])
                ->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
                ->orderByDesc('id')
                ->paginate(),
        ];
    }

    public function name(): ?string
    {
        return 'Дела';
    }

    public function description(): ?string
    {
        return 'Сгенерированные и опубликованные дела Whodoku.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.view'];
    }

    public function layout(): iterable
    {
        return [PuzzleListLayout::class];
    }

    public function remove(Request $request, PuzzleDeleter $deleter): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.puzzles.delete'), 403);

        $deleter->delete(Puzzle::query()->findOrFail($request->integer('puzzle')), $request->user());
        Toast::success('Дело удалено.');

        return to_route('platform.puzzles');
    }
}
