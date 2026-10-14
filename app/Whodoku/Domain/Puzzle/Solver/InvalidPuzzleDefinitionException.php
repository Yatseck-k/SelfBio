<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Solver;

use InvalidArgumentException;
use Whodoku\Domain\Puzzle\Validation\ValidationIssue;

class InvalidPuzzleDefinitionException extends InvalidArgumentException
{
    /** @param list<ValidationIssue> $issues */
    public function __construct(public readonly array $issues)
    {
        parent::__construct('Puzzle definition has structural errors.');
    }
}
