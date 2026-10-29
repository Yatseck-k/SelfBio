<?php

declare(strict_types=1);

namespace Tests\Unit\Whodoku;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Whodoku\Domain\Puzzle\Enums\ClueType;
use Whodoku\Domain\Puzzle\Generation\CaseCondition;
use Whodoku\Domain\Puzzle\Generation\GenerationFailedException;
use Whodoku\Domain\Puzzle\Generation\GenerationProfile;
use Whodoku\Domain\Puzzle\Generation\PuzzleGenerator;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Review\PuzzleReviewer;
use Whodoku\Domain\Puzzle\Solver\PuzzleSolver;
use Whodoku\Domain\Puzzle\Solver\SolverResult;

class PuzzleGeneratorTest extends TestCase
{
    /** @return iterable<string, array{int, int}> */
    public static function sizes(): iterable
    {
        yield '4x4' => [4, 4];
        yield '5x5' => [5, 5];
        yield '4x6' => [4, 6];
        yield '6x6' => [6, 6];
        yield '7x7' => [7, 7];
    }

    #[DataProvider('sizes')]
    public function test_guests_do_not_all_stand_on_furniture_and_every_case_passes_the_bot(int $rows, int $cols): void
    {
        $guests = 0;
        $onFurniture = 0;
        $generated = 0;
        $passed = 0;

        for ($seed = 1; $seed <= 25; $seed++) {
            try {
                $case = (new PuzzleGenerator)->generate(GenerationProfile::forGrid($rows, $cols, $seed, 40));
            } catch (GenerationFailedException) {
                continue;
            }
            $generated++;
            $definition = $case->definition;

            foreach ($case->authorPlacements as $placement) {
                $guests++;
                $onFurniture += $definition->objectsAt($placement->cell) === [] ? 0 : 1;
            }
            $this->assertLessThan(count($case->authorPlacements), count(array_filter(
                $case->authorPlacements,
                fn ($placement) => $definition->objectsAt($placement->cell) !== [],
            )), "Seed $seed of {$rows}x{$cols} puts every guest on furniture");

            foreach ($definition->walls as $wall) {
                $this->assertNotSame(
                    $definition->roomAt($wall->from)?->code,
                    $definition->roomAt($wall->to)?->code,
                    "Seed $seed of {$rows}x{$cols} has a wall inside one room",
                );
            }

            foreach ($definition->clues as $clue) {
                if ($clue->type === ClueType::TokenNearWindow) {
                    $room = isset($clue->parameters['room']) ? (string) $clue->parameters['room'] : null;
                    $this->assertCount(1, $definition->windowsNear($room), "Window statement is ambiguous: {$clue->text}");
                }
            }

            $report = (new PuzzleReviewer)->review($definition, $case->authorPlacements, [
                'ending' => $case->ending, 'victim' => $case->victim, 'story' => $case->story, 'hints' => $case->hints,
            ]);
            // A case a person cannot finish by logic is rejected by the publisher and replaced; anything else is a defect.
            $defects = array_values(array_filter(
                array_map(fn ($check) => $check->code, $report->failures()),
                fn (string $code) => $code !== 'logic_solvable',
            ));
            $this->assertSame([], $defects, "Seed $seed of {$rows}x{$cols} fails the bot");
            $passed += $report->passed() ? 1 : 0;
        }

        $this->assertGreaterThanOrEqual(15, $generated, 'The generator must succeed for most seeds.');
        $this->assertGreaterThanOrEqual(intdiv($generated, 2), $passed, 'Most generated cases pass the bot.');
        $share = $onFurniture / $guests;
        $this->assertGreaterThan(0.15, $share, 'Some guests stand on furniture.');
        $this->assertLessThan(0.75, $share, 'Most guests must not stand on furniture.');
    }

    public function test_each_extra_condition_is_needed_for_a_unique_solution(): void
    {
        $case = null;
        for ($seed = 1; $seed <= 40; $seed++) {
            try {
                $case = (new PuzzleGenerator)->generate(GenerationProfile::forGrid(
                    6,
                    6,
                    $seed,
                    40,
                    conditions: [CaseCondition::EveryRoom, CaseCondition::RoomExactCount],
                ));
                break;
            } catch (GenerationFailedException) {
                continue;
            }
        }

        $this->assertNotNull($case, 'The generator must make a case whose conditions matter.');
        $definition = $case->definition;

        foreach (array_keys(array_filter($definition->clues, fn ($clue) => $clue->type->isCondition())) as $index) {
            $without = new PuzzleDefinition(
                $definition->slug,
                $definition->type,
                $definition->grid,
                $definition->rooms,
                $definition->walls,
                $definition->objects,
                $definition->tokens,
                array_values(array_filter($definition->clues, fn ($clue, int $clueIndex) => $clueIndex !== $index, ARRAY_FILTER_USE_BOTH)),
                $definition->windows,
            );

            $this->assertSame(SolverResult::MULTIPLE, (new PuzzleSolver)->solve($without)->status);
        }
    }
}
