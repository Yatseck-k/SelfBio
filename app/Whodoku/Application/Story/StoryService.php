<?php

declare(strict_types=1);

namespace Whodoku\Application\Story;

use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Application\Player\RankLadder;
use Whodoku\Application\Puzzle\GeneratedPuzzlePublicationException;
use Whodoku\Application\Puzzle\GeneratedPuzzlePublisher;
use Whodoku\Application\Puzzle\PublishOptions;
use Whodoku\Domain\Puzzle\Generation\CaseCondition;
use Whodoku\Domain\Puzzle\Generation\GenerationProfile;
use Whodoku\Models\Player;
use Whodoku\Models\PlayerCaseResult;
use Whodoku\Models\Puzzle;

/**
 * The story «Путь детектива»: ten chapters from the trainee to Sherlock Holmes, each with a lesson and a gift of development points (`config/story.php`). A chapter is a
 * case of its own; chapter N opens when chapter N − 1 is solved. The texts around the case (briefing, outro) live in
 * the config, the case itself is generated and checked like any other.
 */
class StoryService
{
    public function __construct(private readonly PlayerProgress $progress = new PlayerProgress) {}

    /** @return list<int> the numbers of the chapters */
    public function numbers(): array
    {
        return array_keys(config('story.chapters'));
    }

    /** @return array{key: string, name: string, role: string, avatar: string}|null a person of the story */
    public function hero(?string $key): ?array
    {
        $hero = $key === null ? null : config("story.heroes.$key");

        return $hero === null ? null : ['key' => $key, 'name' => $hero['name'], 'role' => $hero['role'], 'avatar' => $hero['avatar']];
    }

    /** @return array<string, mixed>|null the chapter of a story case */
    public function chapterOf(Puzzle $puzzle): ?array
    {
        if ($puzzle->collection !== PublishOptions::COLLECTION_STORY || $puzzle->story_chapter === null) {
            return null;
        }
        $chapter = config("story.chapters.{$puzzle->story_chapter}");

        return $chapter === null ? null : ['number' => (int) $puzzle->story_chapter, ...$chapter, 'speaker' => $this->hero($chapter['speaker'] ?? null)];
    }

    /** Chapter N is open for a signed-in player who has solved chapter N − 1 (chapter 1 is always open). */
    public function canOpen(?Player $player, Puzzle $puzzle): bool
    {
        $number = $puzzle->story_chapter;
        if ($player === null || $number === null || $puzzle->collection !== PublishOptions::COLLECTION_STORY) {
            return false;
        }

        return $number === 1 || $this->solved($player, (int) $number - 1);
    }

    /** What the player sees on the path: every chapter with its state; the case of a locked chapter stays unnamed. */
    public function path(?Player $player): array
    {
        $cases = Puzzle::query()->where('collection', PublishOptions::COLLECTION_STORY)->where('status', 'published')->get()->keyBy('story_chapter');
        $solvedSlugs = $player === null ? [] : array_flip($this->progress->solvedSlugs($player));
        $previousSolved = true;
        $chapters = [];

        foreach (config('story.chapters') as $number => $chapter) {
            $case = $cases->get($number);
            $solved = $case !== null && isset($solvedSlugs[$case->slug]);
            $open = $player !== null && $previousSolved;
            $chapters[] = [
                'number' => $number,
                'title' => $chapter['title'],
                // The rank the player has once the chapter is solved.
                'rank' => RankLadder::forChapters((int) $number)['title'],
                'lesson' => $open || $solved ? ($chapter['lesson'] ?? null) : null,
                'grant' => (int) ($chapter['grant'] ?? 0),
                'difficulty' => $chapter['difficulty'],
                'state' => $solved ? 'solved' : ($open ? 'open' : 'locked'),
                // The briefing is a spoiler of the story: it is read when the chapter is reached.
                'briefing' => $open || $solved ? $chapter['briefing'] : null,
                'speaker' => $open || $solved ? $this->hero($chapter['speaker'] ?? null) : null,
                'outro' => $solved ? $chapter['outro'] : null,
                'slug' => ($open || $solved) && $case !== null ? $case->slug : null,
                'built' => $case !== null,
            ];
            $previousSolved = $solved;
        }

        return $chapters;
    }

    /** How many chapters of the story the player has solved: the rank follows it. */
    public function solvedChapters(Player $player): int
    {
        return $this->chapterCounts([$player->id])[$player->id] ?? 0;
    }

    /**
     * Solved chapters per player (distinct chapters), for the leaderboard and the admin list.
     *
     * @param  list<int>|null  $playerIds  everyone when null
     * @return array<int, int>
     */
    public function chapterCounts(?array $playerIds = null): array
    {
        $query = PlayerCaseResult::query()
            ->join('puzzles', 'puzzles.id', '=', 'player_case_results.puzzle_id')
            ->whereNotNull('player_case_results.solved_at')->whereNotNull('puzzles.story_chapter')
            ->when($playerIds !== null, fn ($query) => $query->whereIn('player_case_results.player_id', $playerIds))
            ->groupBy('player_case_results.player_id')
            ->selectRaw('player_case_results.player_id as player_id, count(distinct puzzles.story_chapter) as chapters');

        return $query->pluck('chapters', 'player_id')->map(fn ($count) => (int) $count)->all();
    }

    /** Development points the story gives for the chapters the player has solved (once per chapter). */
    public function grantedTo(Player $player): int
    {
        // A solve always changes the points, so the key follows every change of the answer.
        return $this->granted["{$player->id}:{$player->points}"] ??= $this->countGrants($player);
    }

    /** @var array<string, int> */
    private array $granted = [];

    private function countGrants(Player $player): int
    {
        $numbers = PlayerCaseResult::query()
            ->join('puzzles', 'puzzles.id', '=', 'player_case_results.puzzle_id')
            ->where('player_case_results.player_id', $player->id)->whereNotNull('player_case_results.solved_at')
            ->whereNotNull('puzzles.story_chapter')->distinct()->pluck('puzzles.story_chapter');

        return (int) $numbers->sum(fn ($number) => (int) config("story.chapters.$number.grant", 0));
    }

    public function solved(Player $player, int $number): bool
    {
        $case = Puzzle::query()->where('collection', PublishOptions::COLLECTION_STORY)->where('story_chapter', $number)->where('status', 'published')->first();

        return $case !== null && in_array($case->slug, $this->progress->solvedSlugs($player), true);
    }

    /** @return list<int> the chapters that have no case yet */
    public function missing(): array
    {
        $have = Puzzle::query()->whereNotNull('story_chapter')->pluck('story_chapter')->all();

        return array_values(array_filter($this->numbers(), fn (int $number) => !in_array($number, $have, true)));
    }

    /**
     * Makes the cases of the chapters that have none yet.
     *
     * @param  (callable(int, bool): void)|null  $progress  called after each chapter with its number and whether it was made
     * @return array{created: int, failed: list<int>}
     */
    public function build(?GeneratedPuzzlePublisher $publisher = null, ?callable $progress = null): array
    {
        $publisher ??= new GeneratedPuzzlePublisher;
        $created = 0;
        $failed = [];

        foreach (config('story.chapters') as $number => $chapter) {
            if (Puzzle::query()->where('story_chapter', $number)->exists()) {
                continue;
            }
            [$rows, $cols] = $chapter['size'];
            $conditions = array_map(CaseCondition::from(...), $chapter['conditions']);
            $options = new PublishOptions(PublishOptions::COLLECTION_STORY, false, 'published', null, $chapter['difficulty'], (int) config('story.variants'), $number);

            try {
                $publisher->publish(GenerationProfile::forGrid($rows, $cols, seed: 7000 + $number * 101, maxAttempts: 12, conditions: $conditions), null, $options);
                $created++;
                if ($progress !== null) {
                    $progress($number, true);
                }
            } catch (GeneratedPuzzlePublicationException) {
                $failed[] = $number;
                if ($progress !== null) {
                    $progress($number, false);
                }
            }
        }

        return ['created' => $created, 'failed' => $failed];
    }
}
