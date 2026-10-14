<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle;

readonly class MapObject
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $code,
        public string $type,
        public string $name,
        public Cell $cell,
        public bool $blocking,
        public bool $occupiable,
        public array $metadata = [],
    ) {}
}
