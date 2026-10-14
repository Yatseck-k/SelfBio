<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Clue;
use Whodoku\Domain\Puzzle\Constraints\ConstraintFactory;
use Whodoku\Domain\Puzzle\Constraints\PlacementState;
use Whodoku\Domain\Puzzle\Enums\ClueType;
use Whodoku\Domain\Puzzle\MapObject;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Solver\PuzzleSolver;
use Whodoku\Domain\Puzzle\Solver\SolverLimits;
use Whodoku\Domain\Puzzle\Solver\SolverResult;
use Whodoku\Domain\Puzzle\Token;

/**
 * One true statement per guest, as varied as the engine allows (room, alone in the room, corner, next to an
 * object, above/below/left/right of an object, on an object). It starts from exact «on the object» statements,
 * which are always unique, and weakens random guests' statements one by one for as long as the solver still
 * finds exactly one placement, so the case has to be deduced with the sudoku rule rather than read off.
 */
class StatementPlanner
{
    public function __construct(
        private readonly CaseTextFactory $texts = new CaseTextFactory,
        private readonly SolverLimits $limits = new SolverLimits,
    ) {}

    /**
     * @param  PuzzleDefinition  $base  the map and the guests; its clues, if any, are the conditions of the case
     * @param  list<Placement>  $placements  the author's complete placement
     * @return list<Clue> one statement per guest, then the conditions
     */
    public function plan(PuzzleDefinition $base, array $placements, SeededRandom $random): array
    {
        $state = new PlacementState($placements);
        $names = array_count_values(array_map(fn (MapObject $object) => $object->name, $base->objects));

        $options = [];
        foreach ($base->tokens as $index => $token) {
            $options[$index] = $this->options($base, $state, $token, $names, $index);
        }

        // Start from the always-unique set (everybody stands «on an object») and weaken random guests'
        // statements while the solver still finds exactly one placement.
        $chosen = [];
        foreach ($options as $index => $byLevel) {
            $chosen[$index] = $random->pick($byLevel[ClueStrength::EXACT] ?? $this->weakest($byLevel));
        }

        foreach ($random->shuffle(array_keys($chosen)) as $index) {
            for ($attempt = 0; $attempt < 4; $attempt++) {
                $current = ClueStrength::of($chosen[$index]->type);
                $weaker = array_values(array_filter($options[$index], fn (array $clues, int $level) => $level < $current, ARRAY_FILTER_USE_BOTH));
                if ($weaker === []) {
                    break;
                }

                $previous = $chosen[$index];
                $chosen[$index] = $random->pick($random->pick($weaker));
                $result = (new PuzzleSolver)->solve($this->withClues($base, $chosen), $this->limits);
                if ($result->status !== SolverResult::UNIQUE) {
                    $chosen[$index] = $previous;
                }
            }
        }

        return $this->withClues($base, $chosen)->clues;
    }

    /**
     * @param  array<int, list<Clue>>  $byLevel
     * @return list<Clue>
     */
    private function weakest(array $byLevel): array
    {
        ksort($byLevel);

        return array_first($byLevel) ?? [];
    }

    /** @param array<int, Clue> $clues */
    private function withClues(PuzzleDefinition $base, array $clues): PuzzleDefinition
    {
        $ordered = [];
        foreach ($clues as $index => $clue) {
            $ordered[] = new Clue($clue->text, $clue->type, $clue->parameters, $index);
        }
        // The conditions of the case stay as they are, after the statements.
        foreach ($base->clues as $position => $condition) {
            $ordered[] = new Clue($condition->text, $condition->type, $condition->parameters, count($clues) + $position);
        }

        return new PuzzleDefinition($base->slug, $base->type, $base->grid, $base->rooms, $base->walls, $base->objects, $base->tokens, $ordered, $base->windows);
    }

    /**
     * Every statement that is true for the guest, grouped by strength.
     *
     * @param  array<string, int>  $names  how many objects carry each name
     * @return array<int, list<Clue>>
     */
    private function options(PuzzleDefinition $base, PlacementState $state, Token $token, array $names, int $order): array
    {
        $cell = $state->cellOf($token->code);
        $room = $cell === null ? null : $base->roomAt($cell);
        if ($cell === null || $room === null) {
            return [ClueStrength::EXACT => []];
        }

        // The furniture the guest uses decides how they speak: a seated guest never «stands» in a room.
        $own = $base->objectsAt($cell)[0] ?? null;

        $candidates = [
            new Clue($this->texts->roomStatement($token, $room, $own), ClueType::TokenInRoom, ['token' => $token->code, 'room' => $room->code]),
            new Clue($this->texts->aloneStatement($token, $room, $own), ClueType::TokenAloneInRoom, ['token' => $token->code, 'room' => $room->code]),
            new Clue($this->texts->cornerStatement($token, $room, $own), ClueType::TokenInRoomCorner, ['token' => $token->code, 'room' => $room->code]),
        ];

        foreach ($base->windowCells() as $near) {
            if ($near->equals($cell)) {
                // Windows are few, so their statements are listed several times to be picked more often.
                for ($copy = 0; $copy < 3; $copy++) {
                    $candidates[] = new Clue($this->texts->windowStatement($token, null, $own), ClueType::TokenNearWindow, ['token' => $token->code]);
                    $candidates[] = new Clue($this->texts->windowStatement($token, $room, $own), ClueType::TokenNearWindow, ['token' => $token->code, 'room' => $room->code]);
                }
            }
        }

        foreach ($base->objects as $object) {
            // «рядом с предметом X» must be unambiguous, so only uniquely named objects are mentioned.
            if (($names[$object->name] ?? 0) !== 1) {
                continue;
            }
            $params = ['token' => $token->code, 'object' => $object->code];
            if ($object->cell->equals($cell)) {
                $candidates[] = new Clue($this->texts->onObjectStatement($token, $object), ClueType::TokenOnObject, $params);

                continue;
            }
            $candidates[] = new Clue($this->texts->adjacentStatement($token, $object, $own), ClueType::TokenAdjacentObject, $params);
            foreach (['north' => ClueType::TokenNorthOfObject, 'south' => ClueType::TokenSouthOfObject, 'west' => ClueType::TokenWestOfObject, 'east' => ClueType::TokenEastOfObject] as $direction => $type) {
                $candidates[] = new Clue($this->texts->directionStatement($token, $object, $direction, $own), $type, $params);
            }
        }

        $factory = new ConstraintFactory;
        $byLevel = [];
        foreach ($candidates as $candidate) {
            if ($factory->create($base, $candidate, $order)->isSatisfied($base, $state)) {
                $byLevel[ClueStrength::of($candidate->type)][] = $candidate;
            }
        }

        return $byLevel;
    }
}
