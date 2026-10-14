<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Release;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Release\ReleaseBusyException;
use Whodoku\Application\Release\ReleaseRunner;
use Whodoku\Application\Release\ReleaseService;

class ReleaseScreen extends Screen
{
    public function __construct(private readonly ReleaseRunner $runner, private readonly ReleaseService $release) {}

    /** @return array<string, mixed> */
    public function query(): iterable
    {
        return [
            'release' => $this->release->status(),
            'current' => $this->runner->current(),
            'runs' => $this->runner->recent(),
            'canRelease' => request()->user()?->hasAccess('platform.puzzles.publish') ?? false,
        ];
    }

    public function name(): ?string
    {
        return 'Выпуск дел';
    }

    public function description(): ?string
    {
        return 'Стартовый набор, гостевые дела, резерв и ежедневные дела: генерация идёт в фоне, каждое дело проверяет бот.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.generate'];
    }

    /** @return Action[] */
    public function commandBar(): iterable
    {
        return [
            Button::make('Сгенерировать стартовый набор')->icon('bs.collection')->method('start', ['kind' => 'starter'])
                ->confirm('Будут созданы недостающие дела стартового набора (до 100). Это займёт несколько минут и идёт в фоне. Продолжить?'),
            Button::make('Пополнить резерв')->icon('bs.archive')->method('start', ['kind' => 'reserve'])
                ->confirm('Будут созданы и проверены дела для будущих выпусков. Продолжить?'),
            Button::make('Создать дела сюжета')->icon('bs.book')->method('start', ['kind' => 'story'])
                ->confirm('Будут созданы недостающие дела десяти глав сюжета «Путь детектива». Это займёт около минуты и идёт в фоне. Продолжить?'),
            Button::make('Выпустить сегодняшние дела')->icon('bs.calendar-check')->type(Color::PRIMARY)->method('start', ['kind' => 'daily'])
                ->confirm('Дела дня станут доступны игрокам сразу. Продолжить?')
                ->canSee(request()->user()?->hasAccess('platform.puzzles.publish') ?? false),
            Button::make('Обновить')->icon('bs.arrow-repeat')->method('refresh'),
        ];
    }

    public function layout(): iterable
    {
        return [Layout::view('orchid.release.status')];
    }

    public function start(Request $request, string $kind): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess($kind === 'daily' ? 'platform.puzzles.publish' : 'platform.puzzles.generate'), 403);

        try {
            $run = $this->runner->start($kind, $request->user());
            Toast::info($run->status === 'running' ? 'Генерация запущена в фоне. Обновляйте страницу, чтобы видеть ход.' : 'Готово: создано дел — '.$run->created.'.');
        } catch (ReleaseBusyException $exception) {
            Toast::warning($exception->getMessage());
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        return to_route('platform.release');
    }

    public function refresh(): RedirectResponse
    {
        return to_route('platform.release');
    }
}
