<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

/** The business-card site: the headings and explanations of its «Контакты» and «Блог» pages. */
class CardSettingsScreen extends PageTextsScreen
{
    public function name(): ?string
    {
        return 'Настройки сайта-визитки';
    }

    public function description(): ?string
    {
        return 'Заголовки и пояснения страниц «Контакты» и «Блог». Сами контакты и записи — в соседних пунктах меню.';
    }

    protected function pages(): array
    {
        return ['contacts', 'blog'];
    }

    protected function route(): string
    {
        return 'platform.selfbio.card';
    }
}
