<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Support\SiteName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

/** The settings of the whole site: for now the name that every page, title and admin screen shows. */
class SiteSettingsScreen extends Screen
{
    public function query(): iterable
    {
        return ['site' => ['name' => config('app.name')]];
    }

    public function name(): ?string
    {
        return 'Настройки сайта';
    }

    public function description(): ?string
    {
        return 'Общие настройки всего сайта: сайта-визитки, игры и личного кабинета.';
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.settings.view'];
    }

    public function commandBar(): iterable
    {
        $canEdit = request()->user()?->hasAccess('platform.selfbio.settings.edit') ?? false;

        return [
            Button::make('Вернуть название по умолчанию')->icon('bs.arrow-counterclockwise')->method('reset')
                ->confirm('Вернуть название из настроек окружения (APP_NAME)?')->canSee($canEdit),
            Button::make('Сохранить')->icon('bs.check-circle')->method('save')->canSee($canEdit),
        ];
    }

    public function layout(): iterable
    {
        return [Layout::rows([
            Input::make('site.name')->title('Название сайта')->required()->maxlength(SiteName::MAX_LENGTH)
                ->help('Показывается в шапках страниц, заголовках вкладок, админке и личном кабинете. Например, «Моя студия». Меняется сразу, пересборка не нужна.'),
        ])];
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.settings.edit'), 403);
        $name = trim((string) $request->validate(['site.name' => ['required', 'string', 'max:'.SiteName::MAX_LENGTH]])['site']['name']);
        if (!SiteName::valid($name)) {
            throw ValidationException::withMessages(['site.name' => 'Название не должно быть пустым или содержать управляющие символы.']);
        }

        SiteName::save($name);
        Toast::success('Название сайта сохранено.');

        return to_route('platform.selfbio.settings');
    }

    public function reset(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.settings.edit'), 403);
        SiteName::reset();
        Toast::info('Название сайта возвращено к значению по умолчанию.');

        return to_route('platform.selfbio.settings');
    }
}
