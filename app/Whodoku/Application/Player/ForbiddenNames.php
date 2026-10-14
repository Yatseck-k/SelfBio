<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

/**
 * Keeps the names of detectives decent: no obscene words (also when letters are swapped for look-alikes or the
 * word is spread over spaces and dashes) and no names that pass for the staff or the service.
 */
final class ForbiddenNames
{
    /** Stems of obscene words; a name that contains one after normalisation is refused. */
    private const array OBSCENE_STEMS = [
        'хуй', 'хуе', 'хуя', 'хуи', 'пизд', 'пезд', 'ебан', 'ебат', 'ебал', 'ебль', 'ебну', 'ебыр', 'еблан', 'заеб', 'уеб',
        'сука', 'суки', 'сучк', 'блят', 'бляд', 'мудак', 'мудил', 'мудозв', 'залуп', 'шлюх', 'пидор', 'пидар', 'пидр',
        'гондон', 'говнo', 'говно', 'гавно', 'дрочи', 'членос', 'ублюд', 'выблядок', 'манда',
        'fuck', 'shit', 'bitch', 'cunt', 'dick', 'nigg',
    ];

    /** Names that would pass for the administration or the service itself. */
    private const array RESERVED = ['админ', 'администратор', 'модератор', 'поддержка', 'killdoku', 'whodoku', 'киллдоку', 'худоку', 'telegram', 'телеграм', 'admin', 'moderator', 'support', 'owner', 'владелец'];

    private const array LOOKALIKES = [
        'a' => 'а', 'b' => 'в', 'c' => 'с', 'e' => 'е', 'h' => 'н', 'k' => 'к', 'm' => 'м', 'o' => 'о', 'p' => 'р',
        't' => 'т', 'x' => 'х', 'y' => 'у', 'ё' => 'е', '0' => 'о', '1' => 'и', '3' => 'з', '4' => 'ч', '6' => 'б',
    ];

    public static function isAllowed(string $name): bool
    {
        $plain = mb_strtolower($name);
        $letters = preg_replace('/[^\p{L}\p{N}]+/u', '', $plain) ?? '';
        $cyrillic = strtr($letters, self::LOOKALIKES);
        // Collapsing repeated letters catches «хууууй» and the like.
        $collapsed = preg_replace('/(\p{L})\1+/u', '$1', $cyrillic) ?? $cyrillic;

        foreach ([$letters, $cyrillic, $collapsed] as $candidate) {
            foreach (self::OBSCENE_STEMS as $stem) {
                if (str_contains($candidate, $stem) || str_contains($candidate, preg_replace('/(\p{L})\1+/u', '$1', $stem) ?? $stem)) {
                    return false;
                }
            }
        }

        return array_all(self::RESERVED, fn ($word) => !($letters === $word || $cyrillic === $word || str_starts_with($letters, $word) || str_starts_with($cyrillic, $word)));
    }
}
