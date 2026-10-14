<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

/** Where a generated case goes: what it is called in the catalog, who may open it, when it appears. */
readonly class PublishOptions
{
    public const COLLECTION_CUSTOM = 'custom';

    public const COLLECTION_BASE = 'base';

    public const COLLECTION_DAILY = 'daily';

    /** The cases of the story «Путь детектива»: they open chapter by chapter, not from the catalog. */
    public const COLLECTION_STORY = 'story';

    public function __construct(
        public string $collection = self::COLLECTION_CUSTOM,
        /** Guests open only cases with this flag; hand-made cases stay open to everybody. */
        public bool $guestAccess = true,
        /** `published` goes live at once, `reserve` waits checked and hidden for a daily release. */
        public string $status = 'published',
        public ?string $releasedOn = null,
        /** When set, a case of another difficulty is skipped and the next variant is tried. */
        public ?int $targetDifficulty = null,
        /** How many variants may be tried (the bot's rejections and the skipped difficulties together). */
        public int $variants = GeneratedPuzzlePublisher::REVIEW_ATTEMPTS,
        /** The chapter of the story this case tells (collection `story`). */
        public ?int $storyChapter = null,
    ) {}
}
