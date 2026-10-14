<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

use Whodoku\Domain\Puzzle\Enums\TokenKind;

readonly class Token
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $code,
        public string $name,
        public TokenKind $kind,
        public bool $countsForRowCol = true,
        public ?string $description = null,
        public ?string $avatar = null,
        public int $sortOrder = 0,
        public array $metadata = [],
    ) {}
}
