<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Whodoku\Domain\Puzzle\Generation\GenerationProfile;
use Whodoku\Models\Puzzle;
use Whodoku\Models\User;

class RandomPuzzleBatchGenerator
{
    public function __construct(
        private readonly GeneratedPuzzlePublisher $publisher = new GeneratedPuzzlePublisher,
    ) {}

    /**
     * Generates and publishes several cases with a random seed and random grid size each.
     * A failed candidate is counted and skipped so it never discards cases already published.
     *
     * @return array{published: list<Puzzle>, failed: int}
     */
    public function generate(int $count, ?User $actor = null, int $maxAttempts = 12): array
    {
        $published = [];
        $failed = 0;

        for ($index = 0; $index < $count; $index++) {
            $profile = GenerationProfile::forGrid(
                random_int(4, 9),
                random_int(4, 9),
                seed: random_int(1, 2147483647),
                maxAttempts: $maxAttempts,
            );

            try {
                $published[] = $this->publisher->publish($profile, $actor);
            } catch (GeneratedPuzzlePublicationException) {
                $failed++;
            }
        }

        return ['published' => $published, 'failed' => $failed];
    }
}
