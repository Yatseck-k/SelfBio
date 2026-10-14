<?php

declare(strict_types=1);

namespace Whodoku\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Whodoku\Application\Story\StoryService;
use Whodoku\Console\WhodokuCommand as Command;

#[Description('Make the cases of the story «Путь детектива» that do not exist yet (one per chapter)')]
#[Signature('puzzles:story')]
class BuildStory extends Command
{
    public function handle(StoryService $story): int
    {
        $result = $story->build();
        $this->line("Создано глав: {$result['created']}.");
        if ($result['failed'] !== []) {
            $this->error('Не удалось создать главы: '.implode(', ', $result['failed']).'. Запустите команду ещё раз.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
