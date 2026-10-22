<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

/** The text of the game pages that the editors write (the explanation of the catalog). */
class GameSettingsScreen extends PageTextsScreen
{
    public function name(): ?string
    {
        return 'Настройки игры';
    }

    public function description(): ?string
    {
        return 'Тексты игровых страниц, которые видят посетители.';
    }

    protected function pages(): array
    {
        return ['puzzles'];
    }

    protected function route(): string
    {
        return 'platform.selfbio.game';
    }
}
