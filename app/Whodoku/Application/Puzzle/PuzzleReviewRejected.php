<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use RuntimeException;
use Whodoku\Domain\Puzzle\Review\ReviewReport;

/** Thrown inside the publication transaction when the bot rejects a case, so nothing of it stays in the database. */
class PuzzleReviewRejected extends RuntimeException
{
    public function __construct(public readonly ReviewReport $report, public readonly string $slug)
    {
        parent::__construct('The bot rejected the generated puzzle.');
    }
}
