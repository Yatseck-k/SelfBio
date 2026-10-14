<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Enums;

enum TokenKind: string
{
    case Character = 'character';
    case Victim = 'victim';
    case Item = 'item';
}
