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
use Whodoku\Application\Puzzle\PuzzleReviewService;
use Whodoku\Models\Puzzle;

class PuzzleValidationScreen extends Screen
{
    private bool $isDraft = false;

    public function query(Puzzle $puzzle): iterable
    {
        $this->isDraft = $puzzle->status === 'draft';
        $validation = $puzzle->auditLogs()->where('action', 'validate')->latest('id')->first();
        $solver = $puzzle->auditLogs()->where('action', 'solve')->latest('id')->first();
        $review = $puzzle->auditLogs()->where('action', 'bot_review')->latest('id')->first();
        $puzzle->loadMissing('clues');
        $conditions = $puzzle->clues->filter(fn ($clue) => in_array($clue->constraint_type, ['EVERY_ROOM_HAS_TOKEN', 'ROOM_TOKEN_LIMIT', 'ROOM_TOKEN_COUNT'], true));

        return [
            'puzzle' => ['title' => $puzzle->title, 'slug' => $puzzle->slug, 'status' => $puzzle->status],
            'validation' => $validation->validation_result ?? [],
            'solverStatus' => $solver->solver_result['status'] ?? 'not_run',
            'botReview' => $review?->validation_result ?? [],
            'statements' => $puzzle->clues->diff($conditions)->sortBy('sort_order')->map(fn ($clue) => [
                'order' => $clue->sort_order + 1,
                'text' => $clue->text,
                'type' => $clue->constraint_type,
            ])->values()->all(),
            'conditions' => $conditions->sortBy('sort_order')->map(fn ($clue) => [
                'text' => $clue->text,
                'type' => $clue->constraint_type,
            ])->values()->all(),
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
            Button::make('Проверить перед публикацией')->icon('bs.check2-circle')->method('runValidation'),
            Button::make('Полная проверка уровня')->icon('bs.robot')->method('runBotReview'),
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

    public function runBotReview(Puzzle $puzzle, Request $request, PuzzleReviewService $review): RedirectResponse
    {
        $report = $review->reviewAndRecord($puzzle, $request->user(), ['validation_screen' => true]);
        $report->passed()
            ? Toast::success('Уровень прошёл полную проверку: карта, показания, условия и решение согласованы.')
            : Toast::error('Бот нашёл ошибки: '.implode('; ', array_map(fn ($check) => $check->title, $report->failures())));

        return to_route('platform.puzzles.validate', $puzzle);
    }
}
