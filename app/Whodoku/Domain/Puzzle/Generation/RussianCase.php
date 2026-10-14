<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Generation;

/**
 * Puts the names of rooms and furniture («барный табурет», «музыкальная гостиная», «кадка с пальмой») into the
 * genitive, accusative, instrumental or prepositional case, so that statements read like speech. It knows the
 * regular patterns of inanimate nouns and their adjectives; whatever it is not sure about it refuses (`null`), and
 * the caller falls back to a quoted nominative. The words after «с», «со» or «из» are left as they are.
 */
final class RussianCase
{
    public const string GENITIVE = 'gen';

    public const string ACCUSATIVE = 'acc';

    public const string INSTRUMENTAL = 'ins';

    public const string PREPOSITIONAL = 'prep';

    /** Nouns that do not change. */
    private const array INDECLINABLE = ['пианино', 'кофе', 'бюро', 'такси', 'радио', 'кино', 'метро', 'фойе'];

    /** Prepositional case after «в» / «на» that is not the regular «-е» (masculine). */
    private const array LOCATIVE = ['сад' => 'саду', 'шкаф' => 'шкафу', 'угол' => 'углу', 'пол' => 'полу', 'мост' => 'мосту'];

    /** Nouns in «-ь» by gender (the ending alone does not tell). */
    private const array SOFT_FEMININE = ['кровать', 'мебель', 'дверь', 'печь'];

    private const array SOFT_MASCULINE = ['рояль'];

    /** @param 'gen'|'acc'|'ins'|'prep' $case */
    public static function phrase(string $text, string $case): ?string
    {
        $text = trim(mb_strtolower($text));
        if ($text === '' || preg_match('/[^а-яё\s-]/u', $text) === 1 || str_contains($text, '-')) {
            return null;
        }

        $tail = '';
        if (preg_match('/^(.+?)(\s(?:с|со|из|для)\s.+)$/u', $text, $match) === 1) {
            [, $text, $tail] = $match;
        }
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $noun = (string) array_pop($words);
        if ($noun === '' || in_array($noun, self::INDECLINABLE, true)) {
            return null;
        }

        $inflected = self::noun($noun, $case);
        if ($inflected === null) {
            return null;
        }
        [$result, $gender] = $inflected;

        $adjectives = [];
        foreach ($words as $word) {
            $form = self::adjective($word, $gender, $case);
            if ($form === null) {
                return null;
            }
            $adjectives[] = $form;
        }

        return implode(' ', [...$adjectives, $result]).$tail;
    }

    /** @return array{string, 'm'|'f'|'n'}|null */
    private static function noun(string $word, string $case): ?array
    {
        // «кладовая», «бильярдная», «мастерская»: a noun that declines like an adjective.
        if (preg_match('/(ая|яя)$/u', $word) === 1) {
            return [self::adjective($word, 'f', $case) ?? $word, 'f'];
        }
        if (preg_match('/(ый|ий|ой)$/u', $word) === 1) {
            return [self::adjective($word, 'm', $case) ?? $word, 'm'];
        }

        if (str_ends_with($word, 'ёр')) {
            return [self::masculine(mb_substr($word, 0, -2).'р', $case, $word), 'm'];
        }
        if (in_array($word, self::SOFT_FEMININE, true)) {
            $stem = mb_substr($word, 0, -1);

            return [match ($case) {
                self::ACCUSATIVE => $word,
                self::INSTRUMENTAL => $stem.'ью',
                default => $stem.'и',
            }, 'f'];
        }
        if (str_ends_with($word, 'ь')) {
            if (!in_array($word, self::SOFT_MASCULINE, true)) {
                return null;
            }
            $stem = mb_substr($word, 0, -1);

            return [match ($case) {
                self::ACCUSATIVE => $word,
                self::INSTRUMENTAL => $stem.'ем',
                self::PREPOSITIONAL => $stem.'е',
                default => $stem.'я',
            }, 'm'];
        }
        if (preg_match('/(ие)$/u', $word) === 1) {
            $stem = mb_substr($word, 0, -1);

            return [match ($case) {
                self::ACCUSATIVE => $word,
                self::INSTRUMENTAL => $stem.'ем',
                default => mb_substr($stem, 0, -1).($case === self::GENITIVE ? 'ия' : 'ии'),
            }, 'n'];
        }
        if (preg_match('/[ея]$/u', $word) === 1) {
            return self::vowelEnding($word, $case);
        }
        if (str_ends_with($word, 'о')) {
            $stem = mb_substr($word, 0, -1);

            return [match ($case) {
                self::ACCUSATIVE => $word,
                self::INSTRUMENTAL => $stem.'ом',
                self::PREPOSITIONAL => $stem.'е',
                default => $stem.'а',
            }, 'n'];
        }
        if (str_ends_with($word, 'а')) {
            return self::feminineA($word, $case);
        }
        if (preg_match('/[бвгджзклмнпрстфхцчшщ]$/u', $word) === 1) {
            return [self::masculine($word, $case, $word), 'm'];
        }

        return null;
    }

    private static function masculine(string $stem, string $case, string $original): string
    {
        return match ($case) {
            self::ACCUSATIVE => $original,
            self::GENITIVE => $stem.'а',
            self::INSTRUMENTAL => $stem.'ом',
            default => self::LOCATIVE[$original] ?? $stem.'е',
        };
    }

    /** @return array{string, 'f'} «-а» and «-я» feminine nouns */
    private static function feminineA(string $word, string $case): array
    {
        $stem = mb_substr($word, 0, -1);
        $afterVelar = preg_match('/[гкхжшчщ]$/u', $stem) === 1;

        return [match ($case) {
            self::ACCUSATIVE => $stem.'у',
            self::GENITIVE => $stem.($afterVelar ? 'и' : 'ы'),
            self::INSTRUMENTAL => $stem.(preg_match('/[жшчщц]$/u', $stem) === 1 ? 'ей' : 'ой'),
            default => $stem.'е',
        }, 'f'];
    }

    /** @return array{string, 'f'|'n'}|null «-я» and «-е» nouns: «спальня», «оранжерея», «скамья» */
    private static function vowelEnding(string $word, string $case): ?array
    {
        if (str_ends_with($word, 'е')) {
            return null; // «-ие» words are handled above; the others are not regular enough
        }
        $stem = mb_substr($word, 0, -1);
        if (str_ends_with($stem, 'ь')) {
            $base = mb_substr($stem, 0, -1);

            return [match ($case) {
                self::ACCUSATIVE => $base.'ью',
                self::GENITIVE => $base.'ьи',
                self::INSTRUMENTAL => $base.'ьёй',
                default => $base.'ье',
            }, 'f'];
        }

        return [match ($case) {
            self::ACCUSATIVE => $stem.'ю',
            self::GENITIVE => $stem.'и',
            self::INSTRUMENTAL => $stem.'ей',
            default => $stem.'е',
        }, 'f'];
    }

    /** @param 'm'|'f'|'n' $gender */
    private static function adjective(string $word, string $gender, string $case): ?string
    {
        if (preg_match('/^(.+?)(ый|ий|ой|ая|яя|ое|ее)$/u', $word, $match) !== 1) {
            return null;
        }
        [, $stem, $ending] = $match;
        $soft = in_array($ending, ['яя', 'ее'], true) || ($ending === 'ий' && preg_match('/[гкхжчшщ]$/u', $stem) !== 1);
        $velar = preg_match('/[гкхжчшщ]$/u', $stem) === 1;

        if ($gender === 'f') {
            return $stem.match ($case) {
                self::ACCUSATIVE => $soft ? 'юю' : 'ую',
                default => $soft ? 'ей' : 'ой',
            };
        }
        if ($case === self::ACCUSATIVE) {
            return $word;
        }

        return $stem.match ($case) {
            self::GENITIVE => $soft ? 'его' : 'ого',
            self::INSTRUMENTAL => $soft || $velar ? 'им' : 'ым',
            default => $soft ? 'ем' : 'ом',
        };
    }
}
