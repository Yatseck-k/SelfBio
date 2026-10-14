<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

/**
 * Original portraits are drawn by the client from a short recipe, so no images are stored or copied:
 * `v1;g=f;skin=2;hair=3;style=1;shirt=4;extra=glasses`. The recipe is part of the case, so a portrait never
 * changes. Styles: 0 short, 1 long, 2 bob, 3 bun, 4 curly, 5 bald, 6 cap.
 */
class AvatarFactory
{
    public const SKINS = 6;

    public const HAIRS = 7;

    public const SHIRTS = 8;

    public function recipe(SeededRandom $random, bool $male): string
    {
        $style = $random->pick($male ? [0, 0, 4, 5, 6] : [1, 1, 2, 3, 4]);
        $extras = $male ? ['none', 'none', 'glasses', 'beard', 'mustache'] : ['none', 'none', 'glasses', 'earrings', 'freckles'];

        return sprintf(
            'v1;g=%s;skin=%d;hair=%d;style=%d;shirt=%d;extra=%s',
            $male ? 'm' : 'f',
            $random->nextInt(self::SKINS),
            $random->nextInt(self::HAIRS),
            $style,
            $random->nextInt(self::SHIRTS),
            $random->pick($extras),
        );
    }
}
