<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Clue;
use Whodoku\Domain\Puzzle\Enums\PuzzleType;
use Whodoku\Domain\Puzzle\Enums\TokenKind;
use Whodoku\Domain\Puzzle\Grid;
use Whodoku\Domain\Puzzle\MapObject;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Room;
use Whodoku\Domain\Puzzle\Solver\PuzzleSolver;
use Whodoku\Domain\Puzzle\Solver\SolverLimits;
use Whodoku\Domain\Puzzle\Solver\SolverResult;
use Whodoku\Domain\Puzzle\Token;
use Whodoku\Domain\Puzzle\Validation\AuthorSolutionValidator;
use Whodoku\Domain\Puzzle\Validation\PuzzleStructureValidator;
use Whodoku\Domain\Puzzle\Wall;
use Whodoku\Domain\Puzzle\Window;

class PuzzleGenerator
{
    /** Share of guests who stand on a piece of furniture; the others stand on a bare cell of their room. */
    private const int GUEST_ON_FURNITURE_PERCENT = 40;

    private readonly ObjectCatalog $catalog;

    private readonly CaseTextFactory $texts;

    private readonly CrimeTexts $crimeTexts;

    public function __construct(
        ?CaseTextFactory $texts = null,
        ?CrimeTexts $crimeTexts = null,
        private readonly SolverLimits $solverLimits = new SolverLimits,
        ?ObjectCatalog $catalog = null,
    ) {
        $this->catalog = $catalog ?? ObjectCatalog::defaults();
        $this->texts = $texts ?? new CaseTextFactory(catalog: $this->catalog);
        $this->crimeTexts = $crimeTexts ?? new CrimeTexts;
    }

    public function generate(GenerationProfile $profile): GeneratedPuzzle
    {
        $random = new SeededRandom($profile->seed);
        $lastStatus = 'not_attempted';

        for ($attempt = 1; $attempt <= $profile->maxAttempts; $attempt++) {
            $candidate = $this->candidate($random, $profile, $attempt);
            if ($candidate === null) {
                $lastStatus = 'no_crime_scene';

                continue;
            }
            if ((new PuzzleStructureValidator)->validate($candidate->definition) !== []) {
                $lastStatus = 'invalid_structure';

                continue;
            }
            if ((new AuthorSolutionValidator)->validate($candidate->definition, $candidate->authorPlacements) !== []) {
                $lastStatus = 'invalid_author_solution';

                continue;
            }

            $result = (new PuzzleSolver)->solve($candidate->definition, $this->solverLimits);
            $lastStatus = $result->status;
            if ($result->status === SolverResult::UNIQUE && $this->samePlacements($candidate->authorPlacements, $result->solution ?? [])) {
                return $candidate;
            }
        }

        throw new GenerationFailedException($profile->maxAttempts, $lastStatus);
    }

    private function candidate(SeededRandom $random, GenerationProfile $profile, int $attempt): ?GeneratedPuzzle
    {
        $type = $profile->type ?? $random->pick(PuzzleType::generated());
        $characters = $this->texts->characters($random, $this->tokenCount($profile->rows, $profile->cols));
        $roomCount = $this->roomCount($profile->rows, $profile->cols);
        if (in_array(CaseCondition::EveryRoom, $profile->conditions, true)) {
            // Every room is occupied: about two guests per room, so that the random placement fills all of them.
            $roomCount = max(2, min($roomCount, (int) ceil(count($characters) / 2)));
        }
        $roomNames = $this->texts->roomNames($random, $roomCount);
        $tokens = [];
        $placements = [];
        $rowPositions = array_slice($random->shuffle(range(0, $profile->rows - 1)), 0, count($characters));
        $colPositions = array_slice($random->shuffle(range(0, $profile->cols - 1)), 0, count($characters));
        $solutionCells = [];

        foreach ($characters as $index => $character) {
            $token = new Token($character['code'], $character['name'], TokenKind::Character, avatar: $character['avatar']);
            $cell = new Cell($rowPositions[$index], $colPositions[$index]);

            $tokens[] = $token;
            $placements[] = new Placement($token->code, $cell);
            $solutionCells[] = $cell;
        }

        $rooms = $this->rooms($random, $profile->rows, $profile->cols, $roomNames);
        $allCells = $this->allCells($profile->rows, $profile->cols);
        $solutionKeys = array_fill_keys(array_map(fn (Cell $cell) => $cell->key(), $solutionCells), true);
        $freeCells = array_values(array_filter($allCells, fn (Cell $cell) => !isset($solutionKeys[$cell->key()])));

        // Figures cover all rows and columns but one (or a few on a rectangle): the cells outside every covered
        // row and column are where the crime left its trace. One of them stays empty, the rest are furnished
        // with impassable objects, so the player finds exactly one empty cell once everybody is placed.
        $coveredRows = array_fill_keys(array_map(fn (Cell $cell) => $cell->row, $solutionCells), true);
        $coveredCols = array_fill_keys(array_map(fn (Cell $cell) => $cell->col, $solutionCells), true);
        $uncovered = $random->shuffle(array_values(array_filter(
            $freeCells,
            fn (Cell $cell) => !isset($coveredRows[$cell->row]) && !isset($coveredCols[$cell->col]),
        )));
        $scene = array_shift($uncovered);
        $reserved = array_fill_keys(array_map(fn (Cell $cell) => $cell->key(), [$scene, ...$uncovered]), true);
        $freeCells = $random->shuffle(array_values(array_filter($freeCells, fn (Cell $cell) => !isset($reserved[$cell->key()]))));
        // Only some figures sit on usable furniture, the rest simply stand in their room; obstacles come from the
        // unusable kinds. Every object is drawn to fit the room it stands in, and names stay unique among
        // clue-bearing objects, so «у предмета X» is unambiguous.
        $solutionEntries = [];
        foreach ($solutionCells as $index => $cell) {
            if ($random->nextInt(100) >= self::GUEST_ON_FURNITURE_PERCENT) {
                $solutionEntries[$index] = null;

                continue;
            }
            $taken = array_column(array_filter($solutionEntries), 'name');
            $pool = array_values(array_filter($this->catalog->usableEntries(), fn (array $entry) => !in_array($entry['name'], $taken, true)));
            $solutionEntries[$index] = $this->pickForRoom($random, $pool, $this->roomAt($rooms, $cell));
        }

        // A guest on a bare cell has little to say about himself, so a piece of furniture of his own room stands right
        // next to him: «рядом с предметом X» keeps such a case solvable on a big map.
        $freeKeys = [];
        foreach ($freeCells as $index => $free) {
            $freeKeys[$free->key()] = $index;
        }
        $anchorCells = [];
        foreach ($solutionCells as $index => $cell) {
            if ($solutionEntries[$index] !== null) {
                continue;
            }
            $home = $this->roomAt($rooms, $cell)->code;
            $near = array_values(array_filter(
                $this->orthogonalNeighbors($cell, $profile->rows, $profile->cols),
                fn (Cell $next) => isset($freeKeys[$next->key()]) && !isset($anchorCells[$next->key()]) && $this->roomAt($rooms, $next)->code === $home,
            ));
            if ($near !== []) {
                $picked = $random->pick($near);
                $anchorCells[$picked->key()] = $picked;
            }
        }
        $freeCells = array_values(array_filter($freeCells, fn (Cell $free) => !isset($anchorCells[$free->key()])));

        // Obstacles are named, impassable furniture inside rooms; hatched blocked cells stay a manual/legacy editor feature.
        $blockedCells = [];
        $extraObjectCount = min(count($freeCells) - 2, max(3, intdiv($profile->rows * $profile->cols, 14)) - count($uncovered));
        $extraObjectCount = max(0, $extraObjectCount);
        $extraObjectCells = [...$uncovered, ...array_slice($freeCells, 0, $extraObjectCount)];
        $extraObjectCount = count($extraObjectCells);
        $decorPool = array_slice($freeCells, $extraObjectCount - count($uncovered));
        // Fill roughly a quarter of the map with furniture so rooms read as lived-in spaces.
        $decorCount = max(0, min(
            count($decorPool),
            (int) round($profile->rows * $profile->cols * 0.27) - count($tokens) - $extraObjectCount,
        ));
        $solutionNames = array_column(array_filter($solutionEntries), 'name');
        $decorPoolEntries = array_values(array_filter($this->catalog->allEntries(), fn (array $entry) => !in_array($entry['name'], $solutionNames, true)));
        $objects = [];

        $anchorNames = $solutionNames;
        foreach (array_values($anchorCells) as $index => $cell) {
            $pool = array_values(array_filter($this->catalog->allEntries(), fn (array $entry) => !in_array($entry['name'], $anchorNames, true)));
            $entry = $this->pickForRoom($random, $pool, $this->roomAt($rooms, $cell));
            $anchorNames[] = $entry['name'];
            $occupiable = $this->catalog->isOccupiable($entry['kind']) ?? true;
            $objects[] = new MapObject('anchor-'.($index + 1), $entry['kind'], $entry['name'], $cell, !$occupiable, $occupiable);
        }
        $decorPoolEntries = array_values(array_filter($this->catalog->allEntries(), fn (array $entry) => !in_array($entry['name'], $anchorNames, true)));

        foreach ($solutionCells as $index => $cell) {
            $entry = $solutionEntries[$index];
            if ($entry === null) {
                continue;
            }
            $objects[] = new MapObject("object-$index", $entry['kind'], $entry['name'], $cell, false, true);
        }

        foreach ($extraObjectCells as $index => $cell) {
            $entry = $this->pickForRoom($random, $this->catalog->unusableEntries(), $this->roomAt($rooms, $cell));
            $objects[] = new MapObject('obstacle-'.($index + 1), $entry['kind'], $entry['name'], $cell, true, false);
        }

        foreach (array_slice($decorPool, 0, $decorCount) as $index => $cell) {
            $entry = $this->pickForRoom($random, $decorPoolEntries, $this->roomAt($rooms, $cell));
            $occupiable = $this->catalog->isOccupiable($entry['kind']) ?? true;
            $objects[] = new MapObject('decor-'.($index + 1), $entry['kind'], $entry['name'], $cell, !$occupiable, $occupiable);
        }

        $base = new PuzzleDefinition(
            "generated-{$profile->seed}-$attempt",
            $type,
            new Grid($profile->rows, $profile->cols, $blockedCells),
            $rooms,
            $this->walls($random, $profile->rows, $profile->cols, $rooms),
            $objects,
            $tokens,
            windows: $this->windows($random, $profile->rows, $profile->cols, $rooms, $solutionCells),
        );
        // Conditions of the whole case ride along in the base, so the statements are weakened with them in mind.
        $conditions = new ConditionPlanner($this->texts)->plan($base, $placements, $profile->conditions, $random);
        // A case promised to carry a condition (a story chapter teaches it) must carry it: a map where it fails is rejected.
        if (count($conditions) !== count($profile->conditions)) {
            return null;
        }
        if ($conditions !== []) {
            $base = new PuzzleDefinition($base->slug, $base->type, $base->grid, $base->rooms, $base->walls, $base->objects, $base->tokens, $conditions, $base->windows);
        }

        // The culprit follows from the layout, so a map without a fitting scene is simply rejected.
        $plan = (new CrimePlanner)->plan($type, $base, $placements, $random, $scene);
        if ($plan === null) {
            return null;
        }

        $definition = new PuzzleDefinition(
            $base->slug, $base->type, $base->grid, $base->rooms, $base->walls, $base->objects, $base->tokens,
            new StatementPlanner($this->texts, $this->solverLimits)->plan($base, $placements, $random),
            $base->windows,
        );

        $victim = (new VictimFactory)->create($plan, $random);
        $title = $this->crimeTexts->title($type, $random);

        return new GeneratedPuzzle(
            $definition,
            $placements,
            $title,
            $this->crimeTexts->description($type, $title, $random),
            $this->crimeTexts->story($plan, $tokens, $random, $victim),
            $this->crimeTexts->hints($plan),
            $this->crimeTexts->ending($plan),
            $victim,
        );
    }

    /**
     * Windows stand on thick lines only: the edge of the map or a border between two rooms. About one per 25 cells.
     *
     * One window always touches a guest's cell, so «возле окна» can be a statement of the case.
     *
     * @param  list<Room>  $rooms
     * @param  list<Cell>  $guestCells
     * @return list<Window>
     */
    private function windows(SeededRandom $random, int $rows, int $cols, array $rooms, array $guestCells): array
    {
        $lines = [];
        $sides = ['north' => [-1, 0], 'south' => [1, 0], 'west' => [0, -1], 'east' => [0, 1]];
        foreach ($this->allCells($rows, $cols) as $cell) {
            foreach ($sides as $side => [$dRow, $dCol]) {
                $across = new Cell($cell->row + $dRow, $cell->col + $dCol);
                $outside = $across->row < 0 || $across->row >= $rows || $across->col < 0 || $across->col >= $cols;
                if (!$outside && $this->roomAt($rooms, $cell)->code === $this->roomAt($rooms, $across)->code) {
                    continue;
                }
                // Each line once, even though both of its cells see it.
                $pair = [$cell->key(), $across->key()];
                sort($pair);
                $key = $outside ? "{$cell->key()}:$side" : implode('|', $pair);
                $lines[$key] ??= new Window($cell, $side);
            }
        }

        $count = max(1, min(3, (int) round($rows * $cols / 25)));

        $lines = $random->shuffle(array_values($lines));
        $guestKeys = array_map(fn (Cell $cell) => $cell->key(), $guestCells);
        $touching = array_values(array_filter($lines, fn (Window $window) => array_any($window->cells(new Grid($rows, $cols)), fn ($cell) => in_array($cell->key(), $guestKeys, true))));
        if ($touching !== []) {
            $first = $touching[0];
            $lines = [$first, ...array_filter($lines, fn (Window $window) => $window !== $first)];
        }

        return array_slice($lines, 0, $count);
    }

    /** More figures on bigger maps, but one less than the shortest side: figures never share a row or column and one line is left free for the scene. */
    private function tokenCount(int $rows, int $cols): int
    {
        return min($rows - 1, $cols - 1, max(4, min(9, (int) round(sqrt($rows * $cols) * 0.8) + 1)));
    }

    /**
     * Most of the time (4 of 5) an entry that fits the room is chosen; otherwise any entry from the pool.
     *
     * @param  list<array{kind: string, name: string}>  $pool
     * @return array{kind: string, name: string}
     */
    private function pickForRoom(SeededRandom $random, array $pool, Room $room): array
    {
        $preferred = array_values(array_filter(
            $pool,
            fn (array $entry) => in_array($entry['kind'], $this->catalog->preferredKinds($room->name), true),
        ));

        return $preferred !== [] && $random->nextInt(5) !== 0 ? $random->pick($preferred) : $random->pick($pool);
    }

    /** One room per ~11 cells, never fewer than four, so large maps get many differently shaped zones. */
    private function roomCount(int $rows, int $cols): int
    {
        return max(4, min(9, (int) round($rows * $cols / 11)));
    }

    /** @param list<string> $roomNames
     * @return list<Room>
     */
    private function rooms(SeededRandom $random, int $rows, int $cols, array $roomNames): array
    {
        // A tightly packed set of anchors can leave one room without a free neighbor; grow again from new anchors.
        for ($try = 0; $try < 25; $try++) {
            $rooms = $this->growRooms($random, $rows, $cols, $roomNames);
            if ($rooms !== null) {
                return $rooms;
            }
        }

        throw new \LogicException('Unable to partition the generated map into connected rooms.');
    }

    /** @param list<string> $roomNames
     * @return list<Room>|null
     */
    private function growRooms(SeededRandom $random, int $rows, int $cols, array $roomNames): ?array
    {
        // Same-parity anchors are never orthogonal neighbors, leaving each one
        // at least one free adjacent cell for its initial connected growth.
        $anchors = array_slice($random->shuffle(array_values(array_filter(
            $this->allCells($rows, $cols),
            fn (Cell $cell) => ($cell->row + $cell->col) % 2 === 0,
        ))), 0, count($roomNames));
        $roomCells = array_map(fn (Cell $cell) => [$cell], $anchors);
        $assigned = [];
        foreach ($anchors as $index => $cell) {
            $assigned[$cell->key()] = $index;
        }

        // Give every room a second connected cell before allowing other regions to grow.
        foreach ($anchors as $index => $anchor) {
            $neighbors = array_values(array_filter(
                $this->orthogonalNeighbors($anchor, $rows, $cols),
                fn (Cell $cell) => !isset($assigned[$cell->key()]),
            ));
            if ($neighbors === []) {
                return null;
            }

            $cell = $random->pick($random->shuffle($neighbors));
            $roomCells[$index][] = $cell;
            $assigned[$cell->key()] = $index;
        }

        while (count($assigned) < $rows * $cols) {
            $frontier = [];
            foreach ($this->allCells($rows, $cols) as $cell) {
                if (isset($assigned[$cell->key()])) {
                    continue;
                }

                foreach ($this->orthogonalNeighbors($cell, $rows, $cols) as $neighbor) {
                    if (isset($assigned[$neighbor->key()])) {
                        $frontier[] = ['cell' => $cell, 'room' => $assigned[$neighbor->key()]];
                    }
                }
            }

            $choice = $random->pick($random->shuffle($frontier));
            $roomCells[$choice['room']][] = $choice['cell'];
            $assigned[$choice['cell']->key()] = $choice['room'];
        }

        return array_map(
            fn (array $cells, int $index) => new Room('room-'.$index, $roomNames[$index], $cells),
            $roomCells,
            array_keys($roomCells),
        );
    }

    /** @return list<Cell> */
    private function allCells(int $rows, int $cols): array
    {
        $cells = [];
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $cells[] = new Cell($row, $col);
            }
        }

        return $cells;
    }

    /** @return list<Cell> */
    private function orthogonalNeighbors(Cell $cell, int $rows, int $cols): array
    {
        $neighbors = [];
        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$rowOffset, $colOffset]) {
            $row = $cell->row + $rowOffset;
            $col = $cell->col + $colOffset;
            if ($row >= 0 && $row < $rows && $col >= 0 && $col < $cols) {
                $neighbors[] = new Cell($row, $col);
            }
        }

        return $neighbors;
    }

    /** @param list<Room> $rooms */
    private function roomAt(array $rooms, Cell $cell): Room
    {
        foreach ($rooms as $room) {
            if ($room->contains($cell)) {
                return $room;
            }
        }

        throw new \LogicException('Generated map cell is not assigned to a room.');
    }

    /** @param list<Room> $rooms
     * @return list<Wall>
     */
    private function walls(SeededRandom $random, int $rows, int $cols, array $rooms): array
    {
        $roomOf = [];
        foreach ($rooms as $room) {
            foreach ($room->cells as $cell) {
                $roomOf[$cell->key()] = $room->code;
            }
        }

        $boundary = [];
        $inner = [];
        foreach ($this->allCells($rows, $cols) as $cell) {
            foreach ([[1, 0], [0, 1]] as [$rowOffset, $colOffset]) {
                $next = new Cell($cell->row + $rowOffset, $cell->col + $colOffset);
                if ($next->row >= $rows || $next->col >= $cols) {
                    continue;
                }

                $wall = new Wall($cell, $next);
                if ($roomOf[$cell->key()] !== $roomOf[$next->key()]) {
                    $boundary[] = $wall;
                } else {
                    $inner[] = $wall;
                }
            }
        }

        // Walls prefer room borders, like doors and partitions on a floor plan, and
        // are spread over different rows and columns before any repeat a line.
        $candidates = array_merge($random->shuffle($boundary), $random->shuffle($inner));
        $count = min(count($candidates), max(3, intdiv($rows * $cols, 12)));
        $picked = [];
        $usedRows = [];
        $usedCols = [];
        foreach ($candidates as $index => $wall) {
            if (count($picked) === $count) {
                break;
            }
            if (isset($usedRows[$wall->from->row]) || isset($usedCols[$wall->from->col])) {
                continue;
            }

            $picked[$index] = $wall;
            $usedRows[$wall->from->row] = true;
            $usedCols[$wall->from->col] = true;
        }
        foreach ($candidates as $index => $wall) {
            if (count($picked) === $count) {
                break;
            }
            $picked[$index] ??= $wall;
        }
        ksort($picked);

        return array_values($picked);
    }

    /** @param list<Placement> $first
     * @param  list<Placement>  $second
     */
    private function samePlacements(array $first, array $second): bool
    {
        return $this->placementMap($first) === $this->placementMap($second);
    }

    /** @param list<Placement> $placements
     * @return array<string, string>
     */
    private function placementMap(array $placements): array
    {
        $map = [];
        foreach ($placements as $placement) {
            $map[$placement->tokenCode] = $placement->cell->key();
        }
        ksort($map);

        return $map;
    }
}
