<?php

declare(strict_types=1);

namespace Whodoku\Domain\Puzzle\Review;

use Whodoku\Domain\Puzzle\Enums\ClueType;

/**
 * Checks of a story case against its chapter: the chapter teaches certain conditions and promises a map size,
 * and its fixed texts (briefing, lesson, outro) must not say things the generated case contradicts.
 */
class StoryChapterReview
{
    private const array CONDITION_CLUES = [
        'every_room' => ClueType::EveryRoomHasToken,
        'room_exact_count' => ClueType::RoomTokenCount,
        'room_limit' => ClueType::RoomTokenLimit,
    ];

    private const array NUMBERS = ['два' => 2, 'двое' => 2, 'три' => 3, 'трое' => 3, 'четыре' => 4, 'четверо' => 4, 'пять' => 5, 'шесть' => 6, 'семь' => 7, 'восемь' => 8, 'девять' => 9, 'двух' => 2, 'трёх' => 3, 'четырёх' => 4, 'пяти' => 5, 'шести' => 6, 'семи' => 7, 'восьми' => 8, 'девяти' => 9];

    /**
     * @param  array<string, mixed>  $chapter  the chapter from config/story.php
     * @param  list<ClueType>  $clueTypes  clue types the case carries
     * @return list<ReviewCheck>
     */
    public function review(array $chapter, array $clueTypes, int $rows, int $cols, int $guests): array
    {
        $missing = [];
        foreach ($chapter['conditions'] ?? [] as $condition) {
            if (!in_array(self::CONDITION_CLUES[$condition], $clueTypes, true)) {
                $missing[] = $condition;
            }
        }
        $size = $chapter['size'] ?? [$rows, $cols];

        return [
            new ReviewCheck('story_conditions', 'Дело содержит все условия, которым учит глава', $missing === [], implode(', ', $missing)),
            new ReviewCheck('story_size', 'Размер карты совпадает с главой', [$rows, $cols] === array_map('intval', $size), "Карта {$rows}×{$cols}, глава обещает {$size[0]}×{$size[1]}."),
            $this->texts($chapter, $guests),
        ];
    }

    /** The fixed texts of a chapter: no counts of people in a room, and a number of guests that matches the map. */
    private function texts(array $chapter, int $guests): ReviewCheck
    {
        $problems = [];
        foreach (['title', 'lesson', 'briefing', 'outro'] as $key) {
            $text = mb_strtolower((string) ($chapter[$key] ?? ''));
            if (preg_match('/ровно (двое|трое|четверо)/u', $text) === 1) {
                $problems[] = "$key: число людей в комнате задано текстом, а в деле оно генерируется";
            }
            if (preg_match_all('/(\p{L}+) гост(?:ей|я|ь)\b/u', $text, $found, PREG_SET_ORDER) > 0) {
                foreach ($found as [, $word]) {
                    $number = self::NUMBERS[$word] ?? null;
                    if ($number !== null && $number !== $guests) {
                        $problems[] = "$key: «{$word} гостей», а на карте {$guests}";
                    }
                }
            }
        }

        return new ReviewCheck('story_texts', 'Тексты главы не противоречат делу', $problems === [], implode('; ', $problems));
    }
}
