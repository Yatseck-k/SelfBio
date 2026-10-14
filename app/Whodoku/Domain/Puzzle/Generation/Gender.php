<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

use Whodoku\Domain\Puzzle\Token;

/** Past-tense agreement for statements and endings: the generated characters are women unless listed here. */
final class Gender
{
    private const array MEN = [
        // Earlier cases
        'boris', 'gleb', 'egor', 'igor', 'maksim',
        'arseniy', 'vilgelm', 'gedeon', 'zakhar', 'ippolit', 'klavdiy', 'leopold', 'nazar', 'oskar', 'platon', 'rodion', 'stanislav', 'timur', 'fedor', 'eduard', 'yulian',
    ];

    public static function past(Token $token, string $feminine, string $masculine): string
    {
        return in_array($token->code, self::MEN, true) ? $masculine : $feminine;
    }

    public static function isMale(string $code): bool
    {
        return in_array($code, self::MEN, true);
    }

    /** @return list<string> */
    public static function menCodes(): array
    {
        return self::MEN;
    }
}
