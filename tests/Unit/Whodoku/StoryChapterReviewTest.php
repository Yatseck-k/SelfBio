<?php

declare(strict_types=1);

namespace Tests\Unit\Whodoku;

use PHPUnit\Framework\TestCase;
use Whodoku\Domain\Puzzle\Enums\ClueType;
use Whodoku\Domain\Puzzle\Review\ReviewCheck;
use Whodoku\Domain\Puzzle\Review\StoryChapterReview;

class StoryChapterReviewTest extends TestCase
{
    private const array CHAPTER = ['title' => 'Дом', 'size' => [4, 4], 'conditions' => ['every_room', 'room_exact_count'], 'briefing' => 'Записи.', 'lesson' => 'Условия.'];

    /** @return array<string, ReviewCheck> */
    private function review(array $chapter, array $clues, int $rows = 4, int $guests = 3): array
    {
        $checks = [];
        foreach ((new StoryChapterReview)->review($chapter, $clues, $rows, $rows, $guests) as $check) {
            $checks[$check->code] = $check;
        }

        return $checks;
    }

    public function test_a_case_with_all_conditions_passes(): void
    {
        $checks = $this->review(self::CHAPTER, [ClueType::EveryRoomHasToken, ClueType::RoomTokenCount]);

        $this->assertTrue($checks['story_conditions']->passed);
        $this->assertTrue($checks['story_size']->passed);
        $this->assertTrue($checks['story_texts']->passed);
    }

    public function test_a_missing_condition_fails(): void
    {
        $checks = $this->review(self::CHAPTER, [ClueType::RoomTokenCount]);

        $this->assertFalse($checks['story_conditions']->passed);
        $this->assertSame('every_room', $checks['story_conditions']->message);
    }

    public function test_a_wrong_size_fails(): void
    {
        $this->assertFalse($this->review(self::CHAPTER, [ClueType::EveryRoomHasToken, ClueType::RoomTokenCount], rows: 5)['story_size']->passed);
    }

    public function test_texts_with_a_fixed_count_or_a_wrong_guest_number_fail(): void
    {
        $count = $this->review([...self::CHAPTER, 'briefing' => 'В одной комнате было ровно двое.'], [ClueType::EveryRoomHasToken, ClueType::RoomTokenCount]);
        $guests = $this->review([...self::CHAPTER, 'briefing' => 'За столом девять гостей.'], [ClueType::EveryRoomHasToken, ClueType::RoomTokenCount], guests: 8);

        $this->assertFalse($count['story_texts']->passed);
        $this->assertFalse($guests['story_texts']->passed);
    }
}
