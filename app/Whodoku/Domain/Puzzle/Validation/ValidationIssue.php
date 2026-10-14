<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Validation;

readonly class ValidationIssue
{
    public function __construct(
        public string $code,
        public string $path,
    ) {}
}
