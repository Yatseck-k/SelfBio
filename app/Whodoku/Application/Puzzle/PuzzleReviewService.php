<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Illuminate\Support\Facades\DB;
use Throwable;
use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Enums\TokenKind;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Review\PuzzleReviewer;
use Whodoku\Domain\Puzzle\Review\ReviewCheck;
use Whodoku\Domain\Puzzle\Review\ReviewReport;
use Whodoku\Domain\Puzzle\Review\StoryChapterReview;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Puzzle;
use Whodoku\Models\User;

/**
 * The bot that checks a case before it goes public: the domain checks (valid, solvable by logic, clear) plus a
 * run through the public service the way a player uses it. The player run happens inside a transaction that
 * is always rolled back, so a draft is played as if it were published without ever becoming visible.
 */
class PuzzleReviewService
{
    public function __construct(
        private readonly ?PuzzleReviewer $reviewer = null,
        private readonly PuzzleDefinitionMapper $mapper = new PuzzleDefinitionMapper,
        private readonly PublicPuzzleService $public = new PublicPuzzleService,
    ) {}

    public function review(Puzzle $puzzle): ReviewReport
    {
        $puzzle->load(['rooms', 'walls', 'objects', 'tokens', 'clues', 'solution', 'hints']);

        try {
            $definition = $this->mapper->fromModel($puzzle);
        } catch (InvalidPuzzleDataException $exception) {
            return new ReviewReport([new ReviewCheck('data', 'Данные дела читаются', false, $exception->getMessage())]);
        }

        $payload = $puzzle->solution?->placements;
        if (!is_array($payload) || $payload === []) {
            return new ReviewReport([new ReviewCheck('author_solution', 'Есть авторская расстановка', false, 'У дела нет сохранённого решения.')]);
        }
        $author = array_map(
            fn (string $code, array $cell) => new Placement($code, new Cell($cell[0], $cell[1])),
            array_keys($payload),
            array_values($payload),
        );

        $reviewer = $this->reviewer ?? new PuzzleReviewer;
        $report = $reviewer->review($definition, $author, [
            'ending' => $puzzle->ending,
            'victim' => $puzzle->victim,
            'story' => $puzzle->story,
            'hints' => $puzzle->hints->pluck('text')->all(),
        ]);

        // Playing a broken case would only throw, so the player run follows a clean domain review.
        $report = $report->withChecks($this->storyChecks($puzzle, $definition));

        return $report->passed() ? $report->withChecks($this->playerRun($puzzle, $payload)) : $report;
    }

    /** @return list<ReviewCheck> a story case is also checked against the chapter it belongs to */
    private function storyChecks(Puzzle $puzzle, PuzzleDefinition $definition): array
    {
        $chapter = $puzzle->story_chapter !== null ? config("story.chapters.{$puzzle->story_chapter}") : null;
        if (!is_array($chapter)) {
            return [];
        }

        return (new StoryChapterReview)->review(
            $chapter, array_map(fn ($clue) => $clue->type, $definition->clues), $puzzle->rows, $puzzle->cols, count(array_filter($definition->tokens, fn ($token) => $token->kind === TokenKind::Character)),
        );
    }

    /** Reviews the case and writes the report to the audit log. */
    public function reviewAndRecord(Puzzle $puzzle, ?User $actor = null, array $context = []): ReviewReport
    {
        $startedAt = hrtime(true);
        $report = $this->review($puzzle);
        $this->record($report, $puzzle, $actor, $context, (int) ((hrtime(true) - $startedAt) / 1_000_000));

        return $report;
    }

    /** @param array<string, mixed> $context */
    public function record(ReviewReport $report, ?Puzzle $puzzle, ?User $actor, array $context = [], ?int $durationMs = null, ?string $slug = null): void
    {
        AdminAuditLog::query()->create([
            'user_id' => $actor?->id, 'actor_email' => $actor?->email, 'puzzle_id' => $puzzle?->id,
            'puzzle_slug' => $puzzle->slug ?? $slug, 'action' => 'bot_review',
            'validation_result' => [...$context, ...$report->toArray()], 'duration_ms' => $durationMs,
        ]);
    }

    /**
     * What a player does: open the case, place the guests, check the answer, ask for hints. The wrong and
     * the incomplete boards must not reveal the ending, and the public data must never carry the solution.
     *
     * @param  array<string, array{int, int}>  $solution
     * @return list<ReviewCheck>
     */
    private function playerRun(Puzzle $puzzle, array $solution): array
    {
        $checks = [];
        DB::beginTransaction();
        try {
            Puzzle::query()->whereKey($puzzle->id)->update(['status' => 'published', 'published_at' => $puzzle->published_at ?? now()]);
            $slug = $puzzle->slug;

            try {
                $detail = $this->public->detail($slug);
                $catalog = collect($this->public->catalog(all: true))->firstWhere('slug', $slug) ?? [];
                $leaks = $this->leakedKeys([$detail, $catalog]);
                $checks[] = new ReviewCheck('api_no_leak', 'Публичные данные не содержат решения и развязки', $leaks === [], implode(', ', $leaks));
            } catch (Throwable $exception) {
                $checks[] = new ReviewCheck('api_no_leak', 'Публичные данные не содержат решения и развязки', false, $exception->getMessage());
            }

            $checks[] = $this->attempt('api_correct', 'Верная расстановка принимается и открывает развязку', function () use ($slug, $solution) {
                $answer = $this->public->check($slug, $solution);

                return ($answer['correct'] ?? false) === true && !empty($answer['ending']) ? null : 'Верный ответ не принят.';
            });

            $checks[] = $this->attempt('api_wrong', 'Ошибочная расстановка отклоняется без развязки', function () use ($slug, $solution) {
                $codes = array_keys($solution);
                if (count($codes) < 2) {
                    return null;
                }
                $wrong = $solution;
                [$wrong[$codes[0]], $wrong[$codes[1]]] = [$solution[$codes[1]], $solution[$codes[0]]];
                $answer = $this->public->check($slug, $wrong);

                return ($answer['correct'] ?? true) === false && !isset($answer['ending']) ? null : 'Ошибочный ответ принят или раскрыл развязку.';
            });

            $checks[] = $this->attempt('api_partial', 'Неполная расстановка не раскрывает развязку', function () use ($slug, $solution) {
                $partial = array_slice($solution, 0, -1, true);
                $answer = $this->public->check($slug, $partial);

                return ($answer['correct'] ?? true) === false && ($answer['complete'] ?? true) === false && !isset($answer['ending']) ? null : 'Неполный ответ не отклонён.';
            });

            $checks[] = $this->attempt('api_hints', 'Три подсказки выдаются', function () use ($slug) {
                foreach ([1, 2, 3] as $level) {
                    if (trim($this->public->hint($slug, $level)['text']) === '') {
                        return "Подсказка $level пуста.";
                    }
                }

                return null;
            });
        } finally {
            DB::rollBack();
        }

        return $checks;
    }

    /** @param callable(): ?string $run returns an error text, or null when the step went well */
    private function attempt(string $code, string $title, callable $run): ReviewCheck
    {
        try {
            $error = $run();
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        return new ReviewCheck($code, $title, $error === null, $error ?? '');
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return list<string>
     */
    private function leakedKeys(array $data): array
    {
        $leaks = [];
        array_walk_recursive($data, function (mixed $value, int|string $key) use (&$leaks): void {
            if (in_array($key, ['ending', 'solution', 'placements', 'culprit'], true)) {
                $leaks[] = (string) $key;
            }
        });
        foreach ($data as $part) {
            if (is_array($part)) {
                foreach (['ending', 'solution', 'placements', 'culprit'] as $key) {
                    if (array_key_exists($key, $part)) {
                        $leaks[] = $key;
                    }
                }
            }
        }

        return array_values(array_unique($leaks));
    }
}
