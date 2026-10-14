<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\Welcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class WelcomeScreen extends Screen
{
    public function query(): iterable
    {
        return ['welcome' => Welcome::query()->first()?->toArray() ?? []];
    }

    public function name(): ?string
    {
        return 'Главная страница SelfBio';
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.welcome.view'];
    }

    public function commandBar(): iterable
    {
        return [Button::make('Сохранить')->icon('bs.check-circle')->method('save')
            ->canSee(request()->user()?->hasAccess('platform.selfbio.welcome.edit') ?? false)];
    }

    public function layout(): iterable
    {
        return [Layout::rows([
            Input::make('welcome.title')->title('Заголовок')->required(),
            TextArea::make('welcome.body')->title('Текст')->required()->rows(12),
        ])];
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.welcome.edit'), 403);
        $data = $request->validate([
            'welcome.title' => ['required', 'string', 'max:255'],
            'welcome.body' => ['required', 'string'],
        ])['welcome'];

        $welcome = Welcome::query()->first() ?? new Welcome;
        $welcome->fill($data)->save();
        Cache::forget('welcome.index.data');
        Toast::success('Главная страница сохранена.');

        return to_route('platform.selfbio.welcome');
    }
}
