<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use RuntimeException;

class GeneratedPuzzlePublicationException extends RuntimeException
{
    public function __construct(public readonly string $reason, ?\Throwable $previous = null)
    {
        parent::__construct("Generated puzzle publication failed: $reason.", previous: $previous);
    }
}
