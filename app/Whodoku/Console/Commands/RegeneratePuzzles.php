<?php

declare(strict_types=1);

namespace Whodoku\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;
use Whodoku\Application\Puzzle\GeneratedPuzzlePublicationException;
use Whodoku\Application\Puzzle\GeneratedPuzzlePublisher;
use Whodoku\Application\Puzzle\PublishOptions;
use Whodoku\Console\WhodokuCommand as Command;
use Whodoku\Domain\Puzzle\Enums\ClueType;
use Whodoku\Domain\Puzzle\Generation\CaseCondition;
use Whodoku\Domain\Puzzle\Generation\GenerationProfile;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\PlayerCaseResult;
use Whodoku\Models\PlayerHintUse;
use Whodoku\Models\Puzzle;

#[Description('Replace published cases in which most guests stand on furniture with fresh checked ones; slug, place and player results stay')]
#[Signature('puzzles:regenerate {slug? : Slug of one case} {--apply : Replace the cases; without it only a report is printed} {--limit= : Replace at most this many cases} {--share=0.7 : Replace cases where more guests than this share stand on furniture}')]
class RegeneratePuzzles extends Command
{
    public function handle(GeneratedPuzzlePublisher $publisher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = $this->option('limit') === null ? null : (int) $this->option('limit');
        $share = (float) $this->option('share');
        $slug = $this->argument('slug');
        $done = 0;
        $failed = 0;

        $puzzles = Puzzle::query()->with(['objects', 'solution', 'clues'])->whereIn('status', ['published', 'reserve'])
            ->when($slug !== null, fn ($query) => $query->where('slug', $slug))->orderBy('id')->get();

        foreach ($puzzles as $puzzle) {
            if ($limit !== null && $done + $failed >= $limit) {
                break;
            }
            if ($this->furnitureShare($puzzle) <= $share) {
                continue;
            }
            $this->line("{$puzzle->slug} ({$puzzle->collection}".($puzzle->story_chapter ? ", глава {$puzzle->story_chapter}" : '').", сложность {$puzzle->difficulty})");
            if (!$apply) {
                $done++;

                continue;
            }

            try {
                $this->replace($puzzle, $publisher);
                $done++;
            } catch (GeneratedPuzzlePublicationException $exception) {
                $failed++;
                $this->line("    <error>не удалось создать замену</error> — дело оставлено как есть ({$exception->getMessage()})");
            }
        }

        $this->newLine();
        $this->line(($apply ? 'Заменено' : 'Будет заменено')." дел: $done, не удалось: $failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** The share of the guests who stand on a piece of furniture in the author's placement. */
    private function furnitureShare(Puzzle $puzzle): float
    {
        $placements = $puzzle->solution?->placements ?? [];
        if ($placements === []) {
            return 0.0;
        }
        $occupied = [];
        foreach ($puzzle->objects as $object) {
            $occupied["{$object->row}:{$object->col}"] = true;
        }
        $on = count(array_filter($placements, fn ($cell) => isset($occupied["{$cell[0]}:{$cell[1]}"])));

        return $on / count($placements);
    }

    /** The new case is made first; only when it is ready does it take the place (slug, results) of the old one. */
    private function replace(Puzzle $old, GeneratedPuzzlePublisher $publisher): void
    {
        $story = $old->story_chapter !== null ? config("story.chapters.{$old->story_chapter}") : null;
        $conditions = $story !== null
            ? array_map(CaseCondition::from(...), $story['conditions'])
            : $this->conditionsOf($old);
        $variants = $story !== null ? (int) config('story.variants') : (int) config('killdoku.release.variants');
        $options = new PublishOptions(
            $old->collection, (bool) $old->guest_access, $old->status, $old->released_on?->toDateString(),
            $old->difficulty, $variants,
        );

        $new = null;
        for ($try = 0; $try < 3 && $new === null; $try++) {
            try {
                $new = $publisher->publish(
                    GenerationProfile::forGrid($old->rows, $old->cols, seed: random_int(1, 2147483647), maxAttempts: 12, conditions: $conditions),
                    null,
                    $options,
                );
            } catch (GeneratedPuzzlePublicationException $exception) {
                if ($try === 2) {
                    throw $exception;
                }
            }
        }

        DB::transaction(function () use ($old, $new): void {
            $slug = $old->slug;
            AdminAuditLog::query()->create([
                'puzzle_id' => $new->id, 'puzzle_slug' => $slug, 'action' => 'regenerate',
                'validation_result' => ['replaced' => $old->slug, 'by' => $new->slug, 'title' => $old->title],
            ]);
            // The results are tied to the slug, so keeping it keeps every solved case and every story chapter of the players.
            PlayerCaseResult::query()->where('puzzle_id', $old->id)->update(['puzzle_id' => $new->id, 'puzzle_slug' => $slug, 'puzzle_title' => $new->title, 'puzzle_type' => $new->type]);
            PlayerCaseResult::query()->where('puzzle_slug', $slug)->whereNull('puzzle_id')->update(['puzzle_id' => $new->id, 'puzzle_title' => $new->title, 'puzzle_type' => $new->type]);
            PlayerHintUse::query()->where('puzzle_id', $old->id)->update(['puzzle_id' => $new->id]);
            $old->delete();
            // A chapter has one case, so the new one takes the number only after the old one is gone.
            $new->forceFill(['slug' => $slug, 'revision' => $old->revision + 1, 'story_chapter' => $old->story_chapter])->save();
        });
        $this->line("    → заменено ({$new->title})");
    }

    /** @return list<CaseCondition> */
    private function conditionsOf(Puzzle $puzzle): array
    {
        $map = [
            ClueType::EveryRoomHasToken->value => CaseCondition::EveryRoom,
            ClueType::RoomTokenCount->value => CaseCondition::RoomExactCount,
            ClueType::RoomTokenLimit->value => CaseCondition::RoomLimit,
        ];

        return array_values(array_unique(
            array_filter(array_map(fn ($clue) => $map[$clue->constraint_type] ?? null, $puzzle->clues->all())),
            SORT_REGULAR,
        ));
    }
}
