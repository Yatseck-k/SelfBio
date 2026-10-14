<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Illuminate\Database\Eloquent\Builder;
use Whodoku\Application\Player\PlayerSkills;
use Whodoku\Application\Story\StoryService;
use Whodoku\Models\Player;
use Whodoku\Models\Puzzle;

/**
 * Who opens which case. Published cases are visible to signed-in players; guests open only the cases flagged
 * for them (the ten guest cases of the starting set, and hand-made cases as before).
 */
class PuzzleAccess
{
    public function __construct(private readonly PlayerSkills $skills = new PlayerSkills, private readonly StoryService $story = new StoryService) {}

    /** @param Builder<Puzzle> $query */
    /** @return Builder<Puzzle> */
    public function visibleTo(Builder $query, ?Player $viewer): Builder
    {
        // The story has its own way in: its cases are not listed in the catalog.
        $query->where('status', 'published')->where('collection', '!=', PublishOptions::COLLECTION_STORY);

        return $viewer === null ? $query->where('guest_access', true) : $query;
    }

    public function canOpen(?Player $viewer, Puzzle $puzzle): bool
    {
        if ($puzzle->status !== 'published') {
            return false;
        }

        if ($puzzle->collection === PublishOptions::COLLECTION_STORY) {
            return $this->story->canOpen($viewer, $puzzle);
        }

        // Guest cases are open to everybody; the rest wait for a signed-in player whose intellect opens the difficulty.
        return $puzzle->guest_access || ($viewer !== null && $this->skills->opensDifficulty($viewer, (int) $puzzle->difficulty));
    }

    /** True when the case is closed only by the intellect of a signed-in player (not by the sign-in). */
    public function closedByLevel(?Player $viewer, Puzzle $puzzle): bool
    {
        return $viewer !== null && $puzzle->status === 'published' && $puzzle->collection !== PublishOptions::COLLECTION_STORY && !$this->canOpen($viewer, $puzzle);
    }

    /** True when a story chapter waits for the one before it. */
    public function closedByChapter(?Player $viewer, Puzzle $puzzle): bool
    {
        return $viewer !== null && $puzzle->collection === PublishOptions::COLLECTION_STORY && !$this->canOpen($viewer, $puzzle);
    }

    /** How many published cases a guest cannot see: the invitation to sign in says how many are waiting. */
    public function hiddenFromGuests(): int
    {
        return Puzzle::query()->where('status', 'published')->where('guest_access', false)->count();
    }
}
