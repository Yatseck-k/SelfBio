<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Orchid\Screen\Action;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;

class PlatformScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        // The few numbers of the cards on the main page; a missing table or an unreachable schema just hides the number.
        return ['stats' => [
            'posts' => rescue(fn (): int => BlogPost::query()->count(), null, false),
            'puzzles' => rescue(fn (): int => DB::connection('whodoku')->table('puzzles')->where('status', 'published')->count(), null, false),
            'players' => rescue(fn (): int => DB::connection('whodoku')->table('players')->count(), null, false),
            'account' => rescue(fn (): int => User::query()->count(), null, false),
        ]];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return config('app.name').' / Whodoku';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Управление контентом '.config('app.name').' и игрой Whodoku.';
    }

    /**
     * The screen's action buttons.
     *
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [];
    }

    /**
     * The screen's layout elements.
     *
     * @return \Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::view('orchid.main'),
        ];
    }
}
