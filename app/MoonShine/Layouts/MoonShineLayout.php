<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Resources\BlogPostResource;
use App\MoonShine\Resources\ContactInfoResource;
use App\MoonShine\Resources\WelcomeResource;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;
use MoonShine\UI\Components\Layout\Layout;

final class MoonShineLayout extends AppLayout
{
    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        return [
            ...parent::menu(),
            MenuGroup::make(static fn () => __('moonshine::ui.custom.settings'), [
                MenuItem::make(
                    static fn () => __('moonshine::ui.custom.contact_info'),
                    ContactInfoResource::class
                )->icon('identification'),
            ]),
            MenuGroup::make(static fn () => __('moonshine::ui.custom.blog'), [
                MenuItem::make(
                    static fn () => __('moonshine::ui.custom.write_blog'),
                    BlogPostResource::class
                )->icon('pencil-square'),
            ]),
            MenuItem::make('Welcomes', WelcomeResource::class),
        ];
    }

    /**
     * @param  ColorManager  $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }

    public function build(): Layout
    {
        return parent::build();
    }
}
