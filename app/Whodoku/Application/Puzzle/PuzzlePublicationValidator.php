<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Illuminate\Support\Facades\DB;
use Throwable;
use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\Solver\PuzzleSolver;
use Whodoku\Domain\Puzzle\Solver\SolverLimits;
use Whodoku\Domain\Puzzle\Solver\SolverResult;
use Whodoku\Domain\Puzzle\Validation\AuthorSolutionValidator;
use Whodoku\Domain\Puzzle\Validation\PuzzleStructureValidator;
use Whodoku\Domain\Puzzle\Validation\ValidationIssue;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Puzzle;
use Whodoku\Models\User;

class PuzzlePublicationValidator
{
    public function __construct(
        private readonly PuzzleDefinitionMapper $mapper = new PuzzleDefinitionMapper,
        private readonly PuzzleSolver $solver = new PuzzleSolver,
        private readonly SolverLimits $solverLimits = new SolverLimits,
    ) {}

    /** @return array{structural_errors: list<array{code: string, path: string}>, author_solution_errors: list<array{code: string, path: string}>, solver_status: string, can_publish: bool} */
    public function validate(Puzzle $puzzle, ?User $actor): array
    {
        abort_unless($actor?->hasAccess('platform.puzzles.validate'), 403);

        $startedAt = hrtime(true);
        $result = $this->inspect($puzzle->fresh() ?? $puzzle);
        $this->audit($puzzle, $actor, 'validate', [
            'status' => $result['can_publish'] ? 'valid' : 'invalid',
            'structural_errors' => $result['structural_errors'],
            'author_solution_errors' => $result['author_solution_errors'],
        ], null, $startedAt);
        $this->audit($puzzle, $actor, 'solve', ['status' => $result['solver_status']], null, $startedAt);

        return $result;
    }

    /** @return array{structural_errors: list<array{code: string, path: string}>, author_solution_errors: list<array{code: string, path: string}>, solver_status: string, can_publish: bool, published: bool} */
    public function publish(Puzzle $puzzle, ?User $actor): array
    {
        abort_unless($actor?->hasAccess('platform.puzzles.publish'), 403);

        return DB::transaction(function () use ($puzzle, $actor): array {
            $locked = Puzzle::query()->whereKey($puzzle->getKey())->lockForUpdate()->firstOrFail();
            $startedAt = hrtime(true);
            $result = $this->inspect($locked);
            $this->audit($locked, $actor, 'validate', [
                'status' => $result['can_publish'] ? 'valid' : 'invalid',
                'structural_errors' => $result['structural_errors'],
                'author_solution_errors' => $result['author_solution_errors'],
            ], null, $startedAt);
            $this->audit($locked, $actor, 'solve', ['status' => $result['solver_status']], null, $startedAt);

            if ($locked->status !== 'draft' || !$result['can_publish']) {
                $this->audit($locked, $actor, 'publish', [
                    'status' => 'rejected',
                    'reason' => $locked->status !== 'draft' ? 'not_draft' : 'validation_failed',
                ], null, $startedAt);

                return [...$result, 'published' => false];
            }

            $locked->forceFill(['status' => 'published', 'published_at' => now()])->save();
            $this->audit($locked, $actor, 'publish', ['status' => 'published'], null, $startedAt);

            return [...$result, 'published' => true];
        });
    }

    /** @return array{structural_errors: list<array{code: string, path: string}>, author_solution_errors: list<array{code: string, path: string}>, solver_status: string, can_publish: bool} */
    private function inspect(Puzzle $puzzle): array
    {
        $structuralErrors = [];
        $authorErrors = [];
        $solverStatus = 'not_run';

        try {
            $definition = $this->mapper->fromModel($puzzle);
            $structuralErrors = $this->safeIssues((new PuzzleStructureValidator)->validate($definition));

            if ($structuralErrors === []) {
                $storedPlacements = $this->placements($puzzle->solution?->placements);
                if ($storedPlacements === null) {
                    $authorErrors = [['code' => 'solution.missing_or_invalid', 'path' => 'solution']];
                } else {
                    $authorErrors = $this->safeIssues((new AuthorSolutionValidator)->validate($definition, $storedPlacements));
                }

                $solverResult = $this->solver->solve($definition, $this->solverLimits);
                $solverStatus = $solverResult->status;
                if ($solverStatus === SolverResult::UNIQUE && !$this->samePlacements($storedPlacements ?? [], $solverResult->solution ?? [])) {
                    $authorErrors[] = ['code' => 'solution.does_not_match_solver', 'path' => 'solution'];
                }
            }
        } catch (Throwable) {
            $structuralErrors[] = ['code' => 'definition.invalid', 'path' => 'definition'];
        }

        return [
            'structural_errors' => $structuralErrors,
            'author_solution_errors' => $authorErrors,
            'solver_status' => $solverStatus,
            'can_publish' => $structuralErrors === [] && $authorErrors === [] && $solverStatus === SolverResult::UNIQUE,
        ];
    }

    /** @param list<ValidationIssue> $issues
     * @return list<array{code: string, path: string}>
     */
    private function safeIssues(array $issues): array
    {
        return array_map(fn (ValidationIssue $issue) => ['code' => $issue->code, 'path' => $issue->path], $issues);
    }

    /** @return list<Placement>|null */
    private function placements(mixed $payload): ?array
    {
        if (!is_array($payload) || $payload === []) {
            return null;
        }

        $placements = [];
        foreach ($payload as $tokenCode => $coordinates) {
            if (!is_array($coordinates) || !array_is_list($coordinates) || count($coordinates) !== 2 || !is_int($coordinates[0]) || !is_int($coordinates[1])) {
                return null;
            }
            $placements[] = new Placement((string) $tokenCode, new Cell($coordinates[0], $coordinates[1]));
        }

        return $placements;
    }

    /** @param list<Placement> $first
     * @param  list<Placement>  $second
     */
    private function samePlacements(array $first, array $second): bool
    {
        $toMap = static function (array $placements): array {
            $map = [];
            foreach ($placements as $placement) {
                $map[$placement->tokenCode] = [$placement->cell->row, $placement->cell->col];
            }
            ksort($map);

            return $map;
        };

        return $toMap($first) === $toMap($second);
    }

    private function audit(Puzzle $puzzle, ?User $actor, string $action, array $validationResult, ?array $solverResult, int $startedAt): void
    {
        AdminAuditLog::query()->create([
            'user_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'puzzle_id' => $puzzle->id,
            'puzzle_slug' => $puzzle->slug,
            'action' => $action,
            'validation_result' => $validationResult,
            'solver_result' => $solverResult,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ]);
    }
}
