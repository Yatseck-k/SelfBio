<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Layouts\Puzzle;

use Illuminate\Support\HtmlString;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Whodoku\Models\Puzzle;

class PuzzleListLayout extends Table
{
    /**
     * @var string
     */
    #[\Override]
    public $target = 'puzzles';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('title', 'Название')
                ->cantHide()
                ->sort()
                ->render(fn (Puzzle $puzzle) => Link::make($puzzle->title)
                    ->route('platform.puzzles.show', ['puzzle' => $puzzle])),

            TD::make('slug', 'Slug')
                ->cantHide()
                ->render(fn (Puzzle $puzzle) => new HtmlString('<code class="text-muted small">'.e($puzzle->slug).'</code>')),

            TD::make('status', 'Статус')
                ->sort()
                ->render(fn (Puzzle $puzzle) => new HtmlString(sprintf(
                    '<span class="badge %s">%s</span>',
                    ['published' => 'bg-success', 'reserve' => 'bg-info text-dark', 'draft' => 'bg-secondary'][$puzzle->status] ?? 'bg-secondary',
                    e(['published' => 'опубликовано', 'reserve' => 'в резерве', 'draft' => 'черновик'][$puzzle->status] ?? $puzzle->status),
                ))),

            TD::make('size', 'Поле')
                ->render(fn (Puzzle $puzzle) => "{$puzzle->rows} × {$puzzle->cols}"),

            TD::make('revision', 'Ревизия')
                ->align(TD::ALIGN_RIGHT),

            TD::make('created_at', 'Создано')
                ->usingComponent(DateTimeSplit::class)
                ->sort(),

            TD::make('published_at', 'Опубликовано')
                ->usingComponent(DateTimeSplit::class)
                ->defaultHidden(),

            TD::make('actions', '')
                ->align(TD::ALIGN_RIGHT)
                ->cantHide()
                ->width('17rem')
                ->render(function (Puzzle $puzzle) {
                    $open = $puzzle->status === 'published'
                        ? Link::make('Открыть')->icon('bs.box-arrow-up-right')->href("/puzzles/{$puzzle->slug}")->target('_blank')->type(Color::PRIMARY)->render()
                        : '';
                    $delete = (request()->user()?->hasAccess('platform.puzzles.delete') ?? false)
                        ? Button::make('Удалить')->icon('bs.trash3')->type(Color::DANGER)
                            ->confirm("Удалить дело «{$puzzle->title}» вместе с картой, показаниями и решением? Это действие нельзя отменить.")
                            ->method('remove', ['puzzle' => $puzzle->id])->render()
                        : '';

                    // Two fixed slots, so the buttons stand in the same columns whether or not the case is open.
                    return new HtmlString('<div class="d-flex justify-content-end gap-2">'
                        .'<div style="width:7.5rem" class="text-start">'.$open.'</div>'
                        .'<div style="width:7.5rem" class="text-start">'.$delete.'</div></div>');
                }),
        ];
    }
}
