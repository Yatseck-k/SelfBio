<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Review;

use Throwable;
use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Enums\ClueType;
use Whodoku\Domain\Puzzle\Generation\CrimePlanner;
use Whodoku\Domain\Puzzle\Generation\Gender;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Solver\PuzzleSolver;
use Whodoku\Domain\Puzzle\Solver\SolverLimits;
use Whodoku\Domain\Puzzle\Solver\SolverResult;
use Whodoku\Domain\Puzzle\Validation\AuthorSolutionValidator;
use Whodoku\Domain\Puzzle\Validation\PuzzleStructureValidator;

/**
 * The bot that plays a finished case before it is published: is it valid, does a person solve it by logic alone,
 * and is it clear (statements name things the map shows once, the culprit follows from the rule of the crime).
 * It works on the definition and plain data, so it can run on a fresh case and on a stored one alike.
 */
class PuzzleReviewer
{
    public function __construct(private readonly SolverLimits $limits = new SolverLimits) {}

    /**
     * @param  list<Placement>  $author  the author's placement
     * @param  array{ending?: array<string, mixed>|null, victim?: array<string, mixed>|null, story?: string|null, hints?: list<string>}  $meta
     */
    public function review(PuzzleDefinition $definition, array $author, array $meta = []): ReviewReport
    {
        $checks = [];

        $structure = (new PuzzleStructureValidator)->validate($definition);
        $checks[] = new ReviewCheck('structure', 'Структура карты и показаний', $structure === [], implode('; ', array_map(fn ($issue) => $issue->code, $structure)));
        if ($structure !== []) {
            return new ReviewReport($checks);
        }

        $solution = (new AuthorSolutionValidator)->validate($definition, $author);
        $checks[] = new ReviewCheck('author_solution', 'Авторская расстановка удовлетворяет всем показаниям', $solution === [], implode('; ', array_map(fn ($issue) => $issue->code, $solution)));
        if ($solution !== []) {
            return new ReviewReport($checks);
        }

        $checks[] = $this->uniqueness($definition, $author);

        $logic = (new LogicalSolver)->solve($definition);
        $sameAsAuthor = $logic->status === LogicalSolveResult::SOLVED && $this->same($logic->placements, $author);
        $checks[] = new ReviewCheck(
            'logic_solvable', 'Дело решается логикой, без перебора',
            $sameAsAuthor,
            match (true) {
                $sameAsAuthor => sprintf('Шагов: %d, самый сложный приём: уровень %d.', count($logic->steps), $logic->hardestLevel),
                $logic->status === LogicalSolveResult::STUCK => 'Логика заходит в тупик: закончить можно только догадкой.',
                $logic->status === LogicalSolveResult::SOLVED => 'Логика приводит к другой расстановке, чем авторская.',
                default => 'Показания противоречат друг другу по ходу решения.',
            },
        );

        array_push($checks, ...$this->clarity($definition));
        array_push($checks, ...$this->crime($definition, $author, $meta));

        return new ReviewReport($checks, $logic->steps, $logic->hardestLevel, $logic->initialCandidates);
    }

    private function uniqueness(PuzzleDefinition $definition, array $author): ReviewCheck
    {
        try {
            $result = (new PuzzleSolver)->solve($definition, $this->limits);
        } catch (Throwable $exception) {
            return new ReviewCheck('unique_solution', 'Решение единственное (solver)', false, $exception->getMessage());
        }
        $ok = $result->status === SolverResult::UNIQUE && $this->same($result->solution ?? [], $author);

        return new ReviewCheck('unique_solution', 'Решение единственное (solver)', $ok, $ok ? '' : "Результат solver: {$result->status}.");
    }

    /** @return list<ReviewCheck> */
    private function clarity(PuzzleDefinition $definition): array
    {
        $checks = [];

        // Each guest speaks once.
        $perGuest = [];
        foreach ($definition->clues as $clue) {
            $code = $clue->parameters['token'] ?? null;
            if (is_string($code)) {
                $perGuest[$code] = ($perGuest[$code] ?? 0) + 1;
            }
        }
        $wrong = array_filter($definition->tokens, fn ($token) => ($perGuest[$token->code] ?? 0) !== 1);
        $checks[] = new ReviewCheck(
            'one_statement_each', 'У каждого гостя ровно одно показание', $wrong === [],
            $wrong === [] ? '' : 'Не так у: '.implode(', ', array_map(fn ($token) => $token->name, $wrong)),
        );

        // Things the statements name exist and are told apart on the map.
        $names = array_count_values(array_map(fn ($object) => $object->name, $definition->objects));
        $objects = [];
        foreach ($definition->objects as $object) {
            $objects[$object->code] = $object;
        }
        $rooms = array_column(array_map(fn ($room) => ['code' => $room->code], $definition->rooms), 'code');
        $problems = [];
        foreach ($definition->clues as $clue) {
            if (isset($clue->parameters['object'])) {
                $object = $objects[$clue->parameters['object']] ?? null;
                if ($object === null) {
                    $problems[] = "предмет «{$clue->parameters['object']}» не найден";
                } elseif (($names[$object->name] ?? 0) !== 1) {
                    $problems[] = "название «{$object->name}» встречается на карте несколько раз";
                }
            }
            if (isset($clue->parameters['room']) && !in_array($clue->parameters['room'], $rooms, true)) {
                $problems[] = "комната «{$clue->parameters['room']}» не найдена";
            }
        }
        $checks[] = new ReviewCheck('named_things', 'Названные предметы и комнаты есть на карте и различимы', $problems === [], implode('; ', array_unique($problems)));

        // «Возле окна» must mean one window: the only one of the map, or the only one touching the named room.
        $vague = [];
        foreach ($definition->clues as $clue) {
            if ($clue->type === ClueType::TokenNearWindow) {
                $room = isset($clue->parameters['room']) ? (string) $clue->parameters['room'] : null;
                if (count($definition->windowsNear($room)) !== 1) {
                    $vague[] = $definition->tokenByCode((string) ($clue->parameters['token'] ?? ''))?->name ?? '?';
                }
            }
        }
        $checks[] = new ReviewCheck(
            'window_clear', 'Показание про окно указывает на одно окно', $vague === [],
            $vague === [] ? '' : 'Неясно, какое окно имеет в виду: '.implode(', ', $vague),
        );

        $texts = array_map(fn ($clue) => $clue->text, $definition->clues);
        $checks[] = new ReviewCheck('no_duplicate_statements', 'Показания не повторяются', count($texts) === count(array_unique($texts)));

        $problems = [];
        foreach ($definition->clues as $clue) {
            $token = $definition->tokenByCode((string) ($clue->parameters['token'] ?? ''));
            if ($token === null || !preg_match('/утверждает: «[яЯ] /u', $clue->text)) {
                continue;
            }
            // The verb right after «Я» agrees with the speaker: «стоял / стояла», «любовался / любовалась».
            $verb = preg_match('/утверждает: «[яЯ] (\S+)/u', $clue->text, $found) === 1 ? $found[1] : '';
            $masculine = preg_match('/л(ся)?$/u', $verb) === 1;
            $feminine = preg_match('/ла(сь)?$/u', $verb) === 1;
            if ((Gender::isMale($token->code) && !$masculine) || (!Gender::isMale($token->code) && !$feminine)) {
                $problems[] = $token->name;
            }
        }
        $checks[] = new ReviewCheck('gender_agreement', 'Род в показаниях согласован с гостем', $problems === [], implode(', ', $problems), ReviewCheck::WARNING);

        $initials = array_map(fn ($token) => mb_substr($token->name, 0, 1), $definition->tokens);
        $uniqueNames = count(array_unique(array_map(fn ($token) => $token->name, $definition->tokens))) === count($definition->tokens);
        $checks[] = new ReviewCheck(
            'guests_distinct', 'Имена и первые буквы гостей различны',
            $uniqueNames && count(array_unique($initials)) === count($initials), '', ReviewCheck::WARNING,
        );

        $broken = array_filter($definition->rooms, fn ($room) => !$this->connected($room->cells));
        $checks[] = new ReviewCheck(
            'rooms_connected', 'Каждая комната — связная область', $broken === [],
            implode(', ', array_map(fn ($room) => $room->name, $broken)), ReviewCheck::WARNING,
        );

        return $checks;
    }

    /**
     * @param  list<Placement>  $author
     * @param  array<string, mixed>  $meta
     * @return list<ReviewCheck>
     */
    private function crime(PuzzleDefinition $definition, array $author, array $meta): array
    {
        $ending = $meta['ending'] ?? null;
        $checks = [];

        if (!is_array($ending) || !isset($ending['scene']['cell'])) {
            return [new ReviewCheck('scene', 'Место преступления: пустая клетка', false, 'У дела нет данных о месте преступления (старый формат).', ReviewCheck::WARNING)];
        }

        $scene = new Cell((int) $ending['scene']['cell'][0], (int) $ending['scene']['cell'][1]);
        $empty = $this->emptyCells($definition, $author);
        $checks[] = new ReviewCheck(
            'scene_unique', 'После расстановки остаётся ровно одна пустая клетка — место преступления',
            count($empty) === 1 && $empty[0]->equals($scene),
            'Свободных клеток вне линий гостей: '.count($empty).'.',
        );

        $codes = (new CrimePlanner)->culpritCodes($definition->type, $definition, $author, $scene);
        $culprit = null;
        foreach ($definition->tokens as $token) {
            if ($token->name === ($ending['culprit'] ?? null)) {
                $culprit = $token->code;
            }
        }
        $ok = $culprit !== null && in_array($culprit, $codes, true) && count($codes) === 1;
        $checks[] = new ReviewCheck('culprit_rule', 'Виновный однозначно следует из правила дела', $ok, $ok ? '' : 'По правилу под подозрение попадают: '.(count($codes)).' гост.');

        $victim = $meta['victim'] ?? null;
        $story = (string) ($meta['story'] ?? '');
        $checks[] = new ReviewCheck(
            'victim_card', 'У дела есть карточка потерпевшего, история называет его',
            is_array($victim) && ($victim['statement'] ?? '') !== '' && str_contains($story, (string) ($victim['name'] ?? "\0")),
            '', ReviewCheck::WARNING,
        );
        $checks[] = new ReviewCheck('hints', 'Есть три подсказки', count($meta['hints'] ?? []) >= 3, '', ReviewCheck::WARNING);

        return $checks;
    }

    /**
     * Cells outside every guest's row and column that can hold something.
     *
     * @param  list<Placement>  $author
     * @return list<Cell>
     */
    private function emptyCells(PuzzleDefinition $definition, array $author): array
    {
        $rows = [];
        $cols = [];
        $taken = [];
        foreach ($author as $placement) {
            $token = $definition->tokenByCode($placement->tokenCode);
            if ($token?->countsForRowCol) {
                $rows[$placement->cell->row] = true;
                $cols[$placement->cell->col] = true;
            }
            $taken[$placement->cell->key()] = true;
        }

        $free = [];
        for ($row = 0; $row < $definition->grid->rows; $row++) {
            for ($col = 0; $col < $definition->grid->cols; $col++) {
                $cell = new Cell($row, $col);
                $closed = $definition->grid->isBlocked($cell) || isset($taken[$cell->key()])
                    || array_filter($definition->objectsAt($cell), fn ($object) => $object->blocking || !$object->occupiable) !== [];
                if (!$closed && !isset($rows[$row]) && !isset($cols[$col])) {
                    $free[] = $cell;
                }
            }
        }

        return $free;
    }

    /** @param list<Cell> $cells */
    private function connected(array $cells): bool
    {
        if ($cells === []) {
            return false;
        }
        $keys = [];
        foreach ($cells as $cell) {
            $keys[$cell->key()] = $cell;
        }
        $seen = [$cells[0]->key() => true];
        $queue = [$cells[0]];
        while ($queue !== []) {
            $cell = array_pop($queue);
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dRow, $dCol]) {
                $next = new Cell($cell->row + $dRow, $cell->col + $dCol);
                if (isset($keys[$next->key()]) && !isset($seen[$next->key()])) {
                    $seen[$next->key()] = true;
                    $queue[] = $next;
                }
            }
        }

        return count($seen) === count($keys);
    }

    /**
     * @param  list<Placement>  $first
     * @param  list<Placement>  $second
     */
    private function same(array $first, array $second): bool
    {
        $map = function (array $placements): array {
            $result = [];
            foreach ($placements as $placement) {
                $result[$placement->tokenCode] = $placement->cell->key();
            }
            ksort($result);

            return $result;
        };

        return $map($first) === $map($second);
    }
}
