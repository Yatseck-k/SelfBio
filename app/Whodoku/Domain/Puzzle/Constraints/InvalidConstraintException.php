<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Constraints;

use InvalidArgumentException;

class InvalidConstraintException extends InvalidArgumentException
{
    public function __construct(public readonly string $path, string $reason)
    {
        parent::__construct("$path: $reason");
    }
}
