<?php

declare(strict_types=1);

namespace Whodoku\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;
use Whodoku\Application\Puzzle\PuzzleDefinitionMapper;
use Whodoku\Application\Puzzle\PuzzleReviewService;
use Whodoku\Console\WhodokuCommand as Command;
use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Enums\ClueType;
use Whodoku\Domain\Puzzle\Generation\CaseTextFactory;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Window;
use Whodoku\Models\Puzzle;
use Whodoku\Models\PuzzleClue;

#[Description('Name the room in «возле окна» statements that do not say which window is meant (text, room and extra windows of that room; the answer stays the same)')]
#[Signature('puzzles:clarify-windows {slug? : Slug of one case} {--apply : Save the changes; without it only a report is printed}')]
class ClarifyWindowStatements extends Command
{
    public function handle(PuzzleDefinitionMapper $mapper, PuzzleReviewService $review): int
    {
        $slug = $this->argument('slug');
        $apply = (bool) $this->option('apply');
        $texts = new CaseTextFactory;
        $fixed = 0;
        $stuck = 0;

        $puzzles = Puzzle::query()->with(['rooms', 'walls', 'objects', 'tokens', 'clues', 'solution'])
            ->when($slug !== null, fn ($query) => $query->where('slug', $slug))->orderBy('id')->get();

        foreach ($puzzles as $puzzle) {
            $definition = $mapper->fromModel($puzzle);
            $placements = $puzzle->solution?->placements ?? [];
            $windows = $definition->windows;
            $changes = [];
            $problem = false;

            foreach ($puzzle->clues as $clue) {
                if ($clue->constraint_type !== ClueType::TokenNearWindow->value) {
                    continue;
                }
                $room = $clue->constraint['room'] ?? null;
                if (count($this->near($definition, $windows, $room)) === 1) {
                    continue;
                }

                $token = $definition->tokenByCode((string) ($clue->constraint['token'] ?? ''));
                $cell = $placements[$token?->code ?? ''] ?? null;
                $home = null;
                if ($token !== null && is_array($cell)) {
                    $at = new Cell($cell[0], $cell[1]);
                    $home = $definition->roomAt($at);
                }
                // The window that serves the guest stays; the other windows of that room go, so the statement means one window.
                $serving = $home === null ? [] : array_values(array_filter(
                    $this->near($definition, $windows, $home->code),
                    fn (Window $window) => array_any($window->cells($definition->grid), fn (Cell $c) => $c->equals($at)),
                ));
                if ($home === null || $serving === []) {
                    $problem = true;
                    $this->line("<error>РУЧНАЯ ПРАВКА</error> {$puzzle->slug}: {$clue->text} (перегенерировать дело)");

                    continue;
                }

                $keep = $serving[0]->key();
                $windows = array_values(array_filter(
                    $windows,
                    fn (Window $window) => $window->key() === $keep || !in_array($window, $this->near($definition, $windows, $home->code), true),
                ));
                $own = $definition->objectsAt($at)[0] ?? null;
                $text = $texts->windowStatement($token, $home, $own);
                $changes[] = [$clue, $home->code, $text];
                $this->line("{$puzzle->slug}: {$clue->text}  →  {$text}");
            }

            if ($problem) {
                $stuck++;
            }
            if ($changes === []) {
                continue;
            }
            $fixed += count($changes);
            if ($apply) {
                $this->save($puzzle, $changes, $windows, $review);
            }
        }

        $this->newLine();
        $this->line(($apply ? 'Исправлено' : 'Будет исправлено')." показаний: $fixed, нужна ручная правка: $stuck.");

        return self::SUCCESS;
    }

    /**
     * @param  list<Window>  $windows
     * @return list<Window> the windows a statement can mean
     */
    private function near(PuzzleDefinition $definition, array $windows, ?string $room): array
    {
        $copy = new PuzzleDefinition(
            $definition->slug, $definition->type, $definition->grid, $definition->rooms, $definition->walls,
            $definition->objects, $definition->tokens, $definition->clues, $windows,
        );

        return $copy->windowsNear($room);
    }

    /**
     * The change is kept only when the bot still passes the case.
     *
     * @param  list<array{PuzzleClue, string, string}>  $changes
     * @param  list<Window>  $windows
     */
    private function save(Puzzle $puzzle, array $changes, array $windows, PuzzleReviewService $review): void
    {
        DB::beginTransaction();
        foreach ($changes as [$clue, $room, $text]) {
            $clue->forceFill(['text' => $text, 'constraint' => [...$clue->constraint, 'room' => $room]])->save();
        }
        $puzzle->forceFill(['windows' => array_map(fn (Window $window) => ['cell' => [$window->cell->row, $window->cell->col], 'side' => $window->side], $windows)])->save();
        $report = $review->review($puzzle->fresh());
        if ($report->passed()) {
            DB::commit();

            return;
        }
        DB::rollBack();
        $this->line("    <error>откат</error>: бот не пропустил дело {$puzzle->slug}: ".implode('; ', array_map(fn ($check) => $check->title, $report->failures())));
    }
}
