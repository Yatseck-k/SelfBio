<?php

declare(strict_types=1);

namespace Tests\Unit\Whodoku;

use PHPUnit\Framework\TestCase;
use Whodoku\Domain\Puzzle\Generation\VictimFactory;

/** Fixed texts around a case must not state numbers: the numbers of a case are generated and the text cannot follow them. */
class StoryTextsTest extends TestCase
{
    public function test_victim_statements_do_not_state_how_many_people_there_were(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(VictimFactory::class))->getFileName());

        $this->assertDoesNotMatchRegularExpression('/нас было (двое|трое|четверо)/u', $source);
    }

    public function test_story_texts_do_not_hardcode_condition_counts(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 3).'/config/story.php');

        $this->assertDoesNotMatchRegularExpression('/ровно (двое|трое|четверо)/u', $source);
    }
}
