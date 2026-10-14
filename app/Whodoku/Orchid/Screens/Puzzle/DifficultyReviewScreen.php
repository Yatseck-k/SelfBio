<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Puzzle;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Puzzle\DifficultyThresholds;
use Whodoku\Application\Puzzle\DifficultyVoting;
use Whodoku\Domain\Puzzle\Generation\DifficultyRating;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Puzzle;

/** The blind check of the difficulty (task 44): the administrator rates a few cases of each level and tunes the thresholds. */
class DifficultyReviewScreen extends Screen
{
    public function __construct(private readonly DifficultyVoting $voting, private readonly DifficultyThresholds $thresholds) {}

    /** @return array<string, mixed> */
    public function query(): iterable
    {
        $user = request()->user();
        $votes = $user === null ? [] : $this->voting->votesOf($user);
        $sample = $this->voting->sample();

        return [
            'rows' => $sample->map(fn (Puzzle $puzzle) => [
                'id' => $puzzle->id, 'slug' => $puzzle->slug, 'title' => $puzzle->title, 'size' => "{$puzzle->rows}×{$puzzle->cols}",
                'mine' => $votes[$puzzle->id] ?? null,
                // The system's rating is shown only after the case has been rated: the check stays blind.
                'system' => isset($votes[$puzzle->id]) ? $puzzle->difficulty : null,
                'score' => isset($votes[$puzzle->id]) ? round($this->voting->score($puzzle), 2) : null,
            ])->all(),
            'suggestion' => $this->voting->suggestion(),
            'thresholds' => $this->thresholds->current(),
            'defaults' => DifficultyRating::THRESHOLDS,
            'custom' => $this->thresholds->isCustom(),
            'agreement' => $user === null ? [0, 0] : $this->voting->agreement($user),
            'canApply' => $user?->hasAccess('platform.puzzles.publish') ?? false,
        ];
    }

    public function name(): ?string
    {
        return 'Оценка сложности';
    }

    public function description(): ?string
    {
        return 'Слепая проверка: сыграйте несколько дел каждого уровня, оцените их звёздами по ощущению — подскажем, как подправить пороги.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.generate'];
    }

    /** @return Action[] */
    public function commandBar(): iterable
    {
        $canApply = request()->user()?->hasAccess('platform.puzzles.publish') ?? false;

        return [
            Button::make('Сохранить оценки')->icon('bs.check-circle')->method('save'),
            Button::make('Применить предложенные пороги')->icon('bs.sliders')->type(Color::PRIMARY)->method('applySuggestion')
                ->confirm('Пороги уровней изменятся, а сложность всех дел будет пересчитана. Продолжить?')->canSee($canApply),
            Button::make('Вернуть пороги по умолчанию')->icon('bs.arrow-counterclockwise')->type(Color::DANGER)->method('resetThresholds')
                ->confirm('Пороги вернутся к значениям модели, сложность дел будет пересчитана. Продолжить?')->canSee($canApply),
        ];
    }

    public function layout(): iterable
    {
        return [Layout::view('orchid.difficulty.review')];
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.puzzles.generate'), 403);
        $request->validate(['stars' => ['nullable', 'array'], 'stars.*' => ['nullable', 'integer', 'between:1,5']]);

        $saved = 0;
        foreach ((array) $request->input('stars', []) as $id => $stars) {
            $puzzle = $stars === null || $stars === '' ? null : Puzzle::query()->find((int) $id);
            if ($puzzle !== null) {
                $this->voting->vote($request->user(), $puzzle, (int) $stars);
                $saved++;
            }
        }
        Toast::success($saved > 0 ? "Оценок сохранено: $saved. Оценка системы показана у тех дел, которые вы оценили." : 'Выберите звёзды у дел, которые сыграли.');

        return to_route('platform.difficulty');
    }

    public function applySuggestion(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.puzzles.publish'), 403);

        $suggestion = $this->voting->suggestion();
        if ($suggestion === null || !$this->thresholds->valid($suggestion['thresholds'])) {
            Toast::warning('Пока нечего применять: нужны оценки дел разных уровней.');

            return to_route('platform.difficulty');
        }

        $before = $this->thresholds->current();
        $this->thresholds->save($suggestion['thresholds']);
        $changed = $this->voting->applyToAll();
        $this->audit($request, ['before' => $before, 'after' => $suggestion['thresholds'], 'rerated' => $changed]);
        Toast::success("Пороги применены. Сложность изменилась у дел: $changed.");

        return to_route('platform.difficulty');
    }

    public function resetThresholds(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.puzzles.publish'), 403);

        $before = $this->thresholds->current();
        $this->thresholds->reset();
        $changed = $this->voting->applyToAll();
        $this->audit($request, ['before' => $before, 'reset' => true, 'rerated' => $changed]);
        Toast::success("Пороги возвращены. Сложность изменилась у дел: $changed.");

        return to_route('platform.difficulty');
    }

    /** @param array<string, mixed> $details */
    private function audit(Request $request, array $details): void
    {
        AdminAuditLog::query()->create(['user_id' => $request->user()?->id, 'actor_email' => $request->user()?->email, 'action' => 'difficulty_thresholds', 'validation_result' => $details]);
    }
}
