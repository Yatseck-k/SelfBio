<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Layouts\Puzzle;

use Orchid\Screen\Field;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Layouts\Rows;
use Whodoku\Domain\Puzzle\Enums\PuzzleType;

class GenerationProfileLayout extends Rows
{
    /**
     * The screen's layout elements.
     *
     * @return Field[]
     */
    public function fields(): array
    {
        $types = ['' => 'Случайный'];
        foreach (PuzzleType::generated() as $type) {
            $types[$type->value] = $type->label();
        }

        return [
            Select::make('profile.type')
                ->options($types)
                ->title('Тип дела')
                ->help('Преступление: у каждого типа свой сюжет и своё правило, по которому определяется виновник.'),

            Input::make('profile.seed')
                ->type('number')
                ->min(1)
                ->max(2147483647)
                ->step(1)
                ->title('Seed')
                ->placeholder('Будет создан автоматически')
                ->help('Необязательный seed для воспроизводимой генерации.'),

            Input::make('profile.max_attempts')
                ->type('number')
                ->min(1)
                ->max(50)
                ->step(1)
                ->required()
                ->title('Лимит попыток')
                ->help('Сколько вариантов генератор проверит до контролируемого отказа.'),

            Select::make('profile.rows')
                ->options([4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9'])
                ->required()
                ->title('Строки карты')
                ->help('Размер карты — от 4 до 9 строк и столбцов.'),

            Select::make('profile.cols')
                ->options([4 => '4', 5 => '5', 6 => '6', 7 => '7', 8 => '8', 9 => '9'])
                ->required()
                ->title('Столбцы карты')
                ->help('Размер карты — от 4 до 9 строк и столбцов.'),
        ];
    }
}
