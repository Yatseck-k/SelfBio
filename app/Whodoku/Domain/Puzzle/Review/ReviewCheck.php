<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Review;

/** One line of the bot's report: what was checked, whether it passed, and how serious a failure is. */
readonly class ReviewCheck
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public function __construct(
        public string $code,
        public string $title,
        public bool $passed,
        public string $message = '',
        public string $severity = self::ERROR,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code, 'title' => $this->title, 'passed' => $this->passed,
            'message' => $this->message, 'severity' => $this->severity,
        ];
    }
}
