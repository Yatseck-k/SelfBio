<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Illuminate\Support\Facades\DB;
use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Generation\DifficultyMetrics;
use Whodoku\Domain\Puzzle\Generation\DifficultyRating;
use Whodoku\Domain\Puzzle\Generation\GeneratedPuzzle;
use Whodoku\Domain\Puzzle\Generation\GenerationFailedException;
use Whodoku\Domain\Puzzle\Generation\GenerationProfile;
use Whodoku\Domain\Puzzle\Generation\PuzzleGenerator;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\Review\LogicalSolver;
use Whodoku\Domain\Puzzle\Solver\PuzzleSolver;
use Whodoku\Domain\Puzzle\Solver\SolverResult;
use Whodoku\Domain\Puzzle\Validation\AuthorSolutionValidator;
use Whodoku\Domain\Puzzle\Validation\PuzzleStructureValidator;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Puzzle;
use Whodoku\Models\User;

class GeneratedPuzzlePublisher
{
    public function __construct(
        private readonly ?PuzzleGenerator $generator = null,
        private readonly PuzzleDefinitionMapper $mapper = new PuzzleDefinitionMapper,
        private readonly ?PuzzleReviewService $review = null,
        private readonly ObjectCatalogService $catalogs = new ObjectCatalogService,
        private readonly DifficultyThresholds $thresholds = new DifficultyThresholds,
    ) {}

    /** How many cases the bot may reject before generation gives up (each retry uses the next seed). */
    public const REVIEW_ATTEMPTS = 6;

    /**
     * Generates a case, lets the bot play it and publishes it only when the bot is satisfied. A rejected case
     * leaves nothing in the database except a line in the audit log; the next seed is tried instead.
     */
    public function publish(GenerationProfile $profile, ?User $actor = null, ?PublishOptions $options = null): Puzzle
    {
        $options ??= new PublishOptions;
        $startedAt = hrtime(true);
        $rejected = 0;
        // The lists of furniture are edited in the admin, so every run starts from their current state.
        $generator = $this->generator ?? new PuzzleGenerator(catalog: $this->catalogs->current());
        $review = $this->review ?? new PuzzleReviewService;

        for ($attempt = 0; $attempt < $options->variants; $attempt++) {
            $current = $attempt === 0 ? $profile : $profile->withSeed($profile->seed + $attempt);

            try {
                $generated = $generator->generate($current);
            } catch (GenerationFailedException $exception) {
                if ($rejected === 0) {
                    throw new GeneratedPuzzlePublicationException('generation_failed', $exception);
                }

                continue;
            }

            // A release asks for a given difficulty: the logic the bot would use tells it before anything is stored.
            if ($options->targetDifficulty !== null && $this->difficultyOf($generated) !== $options->targetDifficulty) {
                continue;
            }

            $solverResult = $this->validate($generated);

            try {
                return DB::transaction(fn (): Puzzle => $this->persist($generated, $profile, $current, $actor, $solverResult, $startedAt, $rejected, $review, $options));
            } catch (PuzzleReviewRejected $rejection) {
                $rejected++;
                $review->record($rejection->report, null, $actor, ['seed' => $current->seed, 'attempt' => $attempt + 1, 'rejected' => true], null, $rejection->slug);
            }
        }

        throw new GeneratedPuzzlePublicationException($rejected > 0 ? 'review_failed' : 'generation_failed');
    }

    private function difficultyOf(GeneratedPuzzle $generated): int
    {
        return DifficultyRating::of($this->metricsOf($generated), $this->thresholds->current());
    }

    private function metricsOf(GeneratedPuzzle $generated): DifficultyMetrics
    {
        $logic = (new LogicalSolver)->solve($generated->definition);

        return DifficultyMetrics::measure($generated->definition, $logic->hardestLevel, $logic->steps, $logic->initialCandidates);
    }

    private function persist(GeneratedPuzzle $generated, GenerationProfile $profile, GenerationProfile $current, ?User $actor, SolverResult $solverResult, int $startedAt, int $rejected, PuzzleReviewService $review, PublishOptions $options): Puzzle
    {
        $puzzle = Puzzle::query()->create([
            'slug' => $this->availableSlug($generated->definition->slug),
            'title' => $generated->title,
            'description' => $generated->description,
            'story' => $generated->story,
            'type' => $generated->definition->type->value,
            'difficulty' => 1,
            'rows' => $generated->definition->grid->rows,
            'cols' => $generated->definition->grid->cols,
            'status' => 'draft',
            'collection' => $options->collection,
            'story_chapter' => $options->storyChapter,
            'guest_access' => $options->guestAccess,
            'released_on' => $options->status === 'published' ? $options->releasedOn : null,
            'revision' => 1,
            'blocked_cells' => $this->cells($generated->definition->grid->blockedCells),
            'windows' => array_map(fn ($window) => ['cell' => [$window->cell->row, $window->cell->col], 'side' => $window->side], $generated->definition->windows),
            'victim' => $generated->victim === [] ? null : $generated->victim,
            'ending' => $generated->ending,
        ]);

        $this->storeDefinition($puzzle, $generated);
        $this->verifyStoredPuzzle($puzzle, $generated->authorPlacements);

        $botStartedAt = hrtime(true);
        $report = $review->review($puzzle);
        if (!$report->passed()) {
            throw new PuzzleReviewRejected($report, $puzzle->slug);
        }

        $metrics = DifficultyMetrics::measure($generated->definition, $report->hardestLevel, $report->steps, $report->initialCandidates);
        $puzzle->update([
            'status' => $options->status,
            'published_at' => $options->status === 'published' ? now() : null,
            'difficulty' => DifficultyRating::of($metrics, $this->thresholds->current()),
            'metrics' => $metrics->toArray(),
        ]);
        $this->audit($puzzle, $actor, $profile, $solverResult, $startedAt);
        $review->record($report, $puzzle, $actor, ['seed' => $current->seed, 'attempt' => $rejected + 1], (int) ((hrtime(true) - $botStartedAt) / 1_000_000));

        return $puzzle->fresh(['rooms', 'walls', 'objects', 'tokens', 'clues', 'solution', 'hints']) ?? $puzzle;
    }

    private function validate(GeneratedPuzzle $generated): SolverResult
    {
        if ((new PuzzleStructureValidator)->validate($generated->definition) !== []) {
            throw new GeneratedPuzzlePublicationException('invalid_structure');
        }
        if ((new AuthorSolutionValidator)->validate($generated->definition, $generated->authorPlacements) !== []) {
            throw new GeneratedPuzzlePublicationException('invalid_author_solution');
        }

        $result = (new PuzzleSolver)->solve($generated->definition);
        if ($result->status !== SolverResult::UNIQUE || !$this->samePlacements($generated->authorPlacements, $result->solution ?? [])) {
            throw new GeneratedPuzzlePublicationException("solver_{$result->status}");
        }

        return $result;
    }

    private function storeDefinition(Puzzle $puzzle, GeneratedPuzzle $generated): void
    {
        $definition = $generated->definition;
        foreach ($definition->rooms as $room) {
            $puzzle->rooms()->create(['code' => $room->code, 'name' => $room->name, 'cells' => $this->cells($room->cells)]);
        }
        foreach ($definition->walls as $wall) {
            $puzzle->walls()->create([
                'row_from' => $wall->from->row, 'col_from' => $wall->from->col,
                'row_to' => $wall->to->row, 'col_to' => $wall->to->col,
            ]);
        }
        foreach ($definition->objects as $object) {
            $puzzle->objects()->create([
                'code' => $object->code, 'type' => $object->type, 'name' => $object->name,
                'row' => $object->cell->row, 'col' => $object->cell->col,
                'blocking' => $object->blocking, 'occupiable' => $object->occupiable, 'metadata' => $object->metadata,
            ]);
        }
        foreach ($definition->tokens as $token) {
            $puzzle->tokens()->create([
                'code' => $token->code, 'name' => $token->name, 'kind' => $token->kind->value,
                'description' => $token->description, 'avatar' => $token->avatar,
                'counts_for_row_col' => $token->countsForRowCol, 'sort_order' => $token->sortOrder, 'metadata' => $token->metadata,
            ]);
        }
        foreach ($definition->clues as $clue) {
            $puzzle->clues()->create([
                'text' => $clue->text, 'constraint_type' => $clue->type->value,
                'constraint' => $clue->parameters, 'sort_order' => $clue->sortOrder,
            ]);
        }
        $puzzle->solution()->create(['placements' => $this->placementPayload($generated->authorPlacements)]);
        foreach ($generated->hints as $index => $hint) {
            $puzzle->hints()->create(['level' => $index + 1, 'text' => $hint, 'sort_order' => $index + 1]);
        }
    }

    /** @param list<Placement> $placements */
    private function verifyStoredPuzzle(Puzzle $puzzle, array $placements): void
    {
        $definition = $this->mapper->fromModel($puzzle->fresh(['rooms', 'walls', 'objects', 'tokens', 'clues']) ?? $puzzle);
        if ((new AuthorSolutionValidator)->validate($definition, $placements) !== []) {
            throw new GeneratedPuzzlePublicationException('stored_solution_invalid');
        }

        $result = (new PuzzleSolver)->solve($definition);
        if ($result->status !== SolverResult::UNIQUE || !$this->samePlacements($placements, $result->solution ?? [])) {
            throw new GeneratedPuzzlePublicationException("stored_solver_{$result->status}");
        }
    }

    private function audit(Puzzle $puzzle, ?User $actor, GenerationProfile $profile, SolverResult $solverResult, int $startedAt): void
    {
        $duration = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $base = ['user_id' => $actor?->id, 'actor_email' => $actor?->email, 'puzzle_id' => $puzzle->id, 'puzzle_slug' => $puzzle->slug, 'duration_ms' => $duration];

        AdminAuditLog::query()->create([...$base, 'action' => 'generate', 'validation_result' => [
            'seed' => $profile->seed,
            'rows' => $profile->rows,
            'cols' => $profile->cols,
            'max_attempts' => $profile->maxAttempts,
            'type' => $puzzle->type,
        ]]);
        AdminAuditLog::query()->create([...$base, 'action' => 'validate', 'validation_result' => ['status' => 'valid']]);
        AdminAuditLog::query()->create([...$base, 'action' => 'solve', 'solver_result' => ['status' => $solverResult->status, 'visited_nodes' => $solverResult->visitedNodes]]);
        AdminAuditLog::query()->create([...$base, 'action' => 'publish', 'validation_result' => ['status' => 'published']]);
    }

    private function availableSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;
        while (Puzzle::query()->where('slug', $slug)->exists()) {
            $slug = "$base-$suffix";
            $suffix++;
        }

        return $slug;
    }

    /** @param list<Cell> $cells
     * @return list<array{int, int}>
     */
    private function cells(array $cells): array
    {
        return array_map(fn ($cell) => [$cell->row, $cell->col], $cells);
    }

    /** @param list<Placement> $placements
     * @return array<string, array{int, int}>
     */
    private function placementPayload(array $placements): array
    {
        $payload = [];
        foreach ($placements as $placement) {
            $payload[$placement->tokenCode] = [$placement->cell->row, $placement->cell->col];
        }
        ksort($payload);

        return $payload;
    }

    /** @param list<Placement> $first
     * @param  list<Placement>  $second
     */
    private function samePlacements(array $first, array $second): bool
    {
        return $this->placementPayload($first) === $this->placementPayload($second);
    }
}
