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
use Whodoku\Application\Puzzle\PuzzlePublicationValidator;
use Whodoku\Models\Puzzle;

class PuzzleValidationScreen extends Screen
{
    private bool $isDraft = false;

    public function query(Puzzle $puzzle): iterable
    {
        $this->isDraft = $puzzle->status === 'draft';
        $validation = $puzzle->auditLogs()->where('action', 'validate')->latest('id')->first();
        $solver = $puzzle->auditLogs()->where('action', 'solve')->latest('id')->first();

        return [
            'puzzle' => ['title' => $puzzle->title, 'slug' => $puzzle->slug, 'status' => $puzzle->status],
            'validation' => $validation->validation_result ?? [],
            'solverStatus' => $solver->solver_result['status'] ?? 'not_run',
        ];
    }

    public function name(): ?string
    {
        return 'Проверка и публикация';
    }

    public function description(): ?string
    {
        return 'Публикация доступна только для корректного черновика с единственным решением.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.validate'];
    }

    /** @return Action[] */
    public function commandBar(): iterable
    {
        return [
            Button::make('Запустить проверку')->icon('bs.check2-circle')->method('runValidation'),
            Button::make('Опубликовать')->icon('bs.upload')->method('publish')
                ->canSee($this->isDraft && (request()->user()?->hasAccess('platform.puzzles.publish') ?? false)),
        ];
    }

    public function layout(): iterable
    {
        return [Layout::view('orchid.puzzle.validation')];
    }

    public function runValidation(Puzzle $puzzle, Request $request, PuzzlePublicationValidator $validator): RedirectResponse
    {
        $result = $validator->validate($puzzle, $request->user());
        Toast::info($result['can_publish'] ? 'Проверка пройдена. Дело можно опубликовать.' : 'Проверка завершена. Исправьте ошибки перед публикацией.');

        return to_route('platform.puzzles.validate', $puzzle);
    }

    public function publish(Puzzle $puzzle, Request $request, PuzzlePublicationValidator $validator): RedirectResponse
    {
        $result = $validator->publish($puzzle, $request->user());
        if ($result['published']) {
            Toast::success('Дело опубликовано.');
        } else {
            Toast::error('Дело не опубликовано. Проверьте результаты и убедитесь, что оно осталось черновиком.');
        }

        return to_route('platform.puzzles.validate', $puzzle);
    }
}
