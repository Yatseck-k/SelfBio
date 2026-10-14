<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Puzzle;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Repository;
use Orchid\Screen\Screen;
use Orchid\Screen\Sight;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Player\PlayAnalytics;
use Whodoku\Application\Puzzle\PuzzleDeleter;
use Whodoku\Application\Puzzle\PuzzleReviewService;
use Whodoku\Domain\Puzzle\Generation\DifficultyMetrics;
use Whodoku\Domain\Puzzle\Generation\DifficultyRating;
use Whodoku\Models\Puzzle;
use Whodoku\Orchid\Layouts\Puzzle\PuzzleAuditLogLayout;

class PuzzleDetailsScreen extends Screen
{
    public string $publicSlug = '';

    /**
     * @return array<string, mixed>
     */
    public function query(Puzzle $puzzle): iterable
    {
        return [
            'details' => new Repository([
                'title' => $puzzle->title,
                'slug' => $puzzle->slug,
                'status' => $puzzle->status,
                'size' => "{$puzzle->rows} × {$puzzle->cols}",
                'difficulty' => "{$puzzle->difficulty} / 5",
                'rating' => $this->ratingSummary($puzzle),
                'revision' => $puzzle->revision,
                'published_at' => $puzzle->published_at?->format('d.m.Y H:i') ?? '—',
            ]),
            'bot' => new Repository($this->botSummary($puzzle)),
            'play' => new Repository($this->playSummary($puzzle)),
            'publicSlug' => $puzzle->slug,
            'auditLogs' => $puzzle->auditLogs()
                ->select(['id', 'puzzle_id', 'actor_email', 'action', 'validation_result', 'solver_result', 'duration_ms', 'created_at'])
                ->latest('id')
                ->paginate(),
        ];
    }

    /** For the author only: the four factors of the rating (players see just the stars). */
    private function ratingSummary(Puzzle $puzzle): string
    {
        if (!is_array($puzzle->metrics)) {
            return 'не измерено (php artisan puzzles:rate)';
        }

        $metrics = DifficultyMetrics::fromArray($puzzle->metrics);
        $parts = DifficultyRating::parts($metrics);

        return sprintf(
            'логика %d%% · ясность %d%% · размер %d%% · условия %d%% (условий в деле: %d, шагов логики: %d, из них сложнее вычёркивания: %d)',
            round($parts['logic'] * 100), round($parts['clarity'] * 100), round($parts['size'] * 100), round($parts['conditions'] * 100),
            $metrics->conditions, $metrics->steps, $metrics->deepSteps,
        );
    }

    public function name(): ?string
    {
        return 'Карточка дела';
    }

    public function description(): ?string
    {
        return 'Безопасные метаданные и история генерации.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.view'];
    }

    /**
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Открыть публичную страницу')
                ->icon('bs.box-arrow-up-right')
                ->href("/puzzles/{$this->publicSlug}"),
            Link::make('Редактировать карту')
                ->icon('bs.grid-3x3')
                ->route('platform.puzzles.map', ['puzzle' => request()->route('puzzle')])
                ->canSee(request()->user()?->hasAccess('platform.puzzles.edit') ?? false),
            Link::make('Проверить и опубликовать')
                ->icon('bs.shield-check')
                ->route('platform.puzzles.validate', ['puzzle' => request()->route('puzzle')])
                ->canSee(request()->user()?->hasAccess('platform.puzzles.validate') ?? false),
            Button::make('Перепроверить ботом')
                ->icon('bs.robot')
                ->method('review')
                ->canSee(request()->user()?->hasAccess('platform.puzzles.validate') ?? false),
            Button::make('Удалить дело')
                ->icon('bs.trash3')
                ->type(Color::DANGER)
                ->confirm('Дело будет удалено вместе с картой, показаниями и решением. Это действие нельзя отменить.')
                ->method('delete')
                ->canSee(request()->user()?->hasAccess('platform.puzzles.delete') ?? false),
        ];
    }

    public function review(Puzzle $puzzle, Request $request, PuzzleReviewService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.puzzles.validate'), 403);

        $report = $service->reviewAndRecord($puzzle, $request->user(), ['manual' => true]);
        $report->passed()
            ? Toast::success('Бот прошёл дело: решаемо логикой, всё понятно.')
            : Toast::error('Бот нашёл проблемы: '.implode('; ', array_map(fn ($check) => $check->title, $report->failures())));

        return back();
    }

    /** @return array<string, string> */
    private function playSummary(Puzzle $puzzle): array
    {
        $stats = (new PlayAnalytics)->forCase($puzzle->slug, $puzzle->revision);
        if ($stats['opened'] === 0) {
            return ['summary' => 'Зарегистрированные игроки ещё не открывали это дело.', 'time' => '—', 'hints' => '—'];
        }
        $minutes = $stats['average_seconds'] === null ? '—' : intdiv($stats['average_seconds'], 60).' мин '.($stats['average_seconds'] % 60).' с';

        return [
            'summary' => "Открыли: {$stats['opened']}, решили: {$stats['solved']} ({$stats['rate']} %)",
            'time' => $minutes,
            'hints' => ($stats['average_hints'] ?? '—').' подсказок, '.($stats['average_checks'] ?? '—').' проверок',
        ];
    }

    /** @return array<string, string> */
    private function botSummary(Puzzle $puzzle): array
    {
        $log = $puzzle->auditLogs()->where('action', 'bot_review')->latest('id')->first();
        $result = $log?->validation_result;
        if (!is_array($result)) {
            return ['verdict' => 'Дело ещё не проверялось ботом.', 'checks' => '', 'logic' => ''];
        }

        $problems = [];
        $lines = [];
        foreach ($result['checks'] ?? [] as $check) {
            $mark = $check['passed'] ? '✓' : ($check['severity'] === 'error' ? '✗' : '!');
            $lines[] = e("$mark {$check['title']}".($check['message'] !== '' ? " — {$check['message']}" : ''));
            $problems[] = $check['passed'] ? null : $check['title'];
        }
        $steps = array_map(fn (array $step) => e("{$step['level']}. {$step['note']}"), $result['logic'] ?? []);

        return [
            'verdict' => ($result['passed'] ?? false ? 'Пройдено' : 'Не пройдено')
                .sprintf(' · шагов логики: %d, самый сложный приём: уровень %d · %s', $result['steps'] ?? 0, $result['hardest_level'] ?? 0, $log->created_at->format('d.m.Y H:i')),
            'checks' => implode('<br>', $lines),
            'logic' => implode('<br>', $steps),
        ];
    }

    public function delete(Puzzle $puzzle, Request $request, PuzzleDeleter $deleter): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.puzzles.delete'), 403);

        $deleter->delete($puzzle, $request->user());
        Toast::success('Дело удалено.');

        return to_route('platform.puzzles');
    }

    public function layout(): iterable
    {
        return [
            Layout::legend('details', [
                Sight::make('title', 'Название'),
                Sight::make('slug', 'Slug'),
                Sight::make('status', 'Статус'),
                Sight::make('size', 'Поле'),
                Sight::make('difficulty', 'Сложность'),
                Sight::make('rating', 'Из чего сложность'),
                Sight::make('revision', 'Ревизия'),
                Sight::make('published_at', 'Опубликовано'),
            ])->title('Дело'),
            Layout::legend('bot', [
                Sight::make('verdict', 'Вердикт'),
                Sight::make('checks', 'Проверки')->render(fn (Repository $bot) => $bot->get('checks')),
                Sight::make('logic', 'Решение логикой по шагам')->render(fn (Repository $bot) => $bot->get('logic')),
            ])->title('Проверка ботом'),
            Layout::legend('play', [
                Sight::make('summary', 'Игроки'),
                Sight::make('time', 'Среднее время решения'),
                Sight::make('hints', 'В среднем на решение'),
            ])->title('Как играют'),
            PuzzleAuditLogLayout::class,
        ];
    }
}
