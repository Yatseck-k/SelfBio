<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Account;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class AccountUserEditScreen extends Screen
{
    private const int MIN_PASSWORD = 12;

    public function query(?User $user = null): iterable
    {
        $user ??= new User;

        return ['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]];
    }

    public function name(): ?string
    {
        return request()->route('user') ? 'Пользователь личного кабинета' : 'Новый пользователь';
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.account.edit'];
    }

    public function commandBar(): iterable
    {
        return [Button::make('Сохранить')->icon('bs.check-circle')->method('save')];
    }

    public function layout(): iterable
    {
        $creating = !request()->route('user');

        return [Layout::rows([
            Input::make('user.name')->title('Имя')->required(),
            Input::make('user.email')->title('Email')->type('email')->required()->help('Это логин для входа.'),
            Input::make('user.password')->title($creating ? 'Пароль' : 'Новый пароль')->type('password')->autocomplete('new-password')
                ->required($creating)->help($creating ? 'Не короче '.self::MIN_PASSWORD.' символов.' : 'Оставьте пустым, чтобы не менять. Не короче '.self::MIN_PASSWORD.' символов.'),
        ])];
    }

    public function save(Request $request, ?User $user = null): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.account.edit'), 403);
        $user ??= new User;

        $data = $request->validate([
            'user.name' => ['required', 'string', 'max:255'],
            'user.email' => ['required', 'email', 'max:255', Rule::unique('pgsql.users', 'email')->ignore($user->id)],
            'user.password' => [$user->exists ? 'nullable' : 'required', 'string', 'min:'.self::MIN_PASSWORD],
        ])['user'];

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (!empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        Toast::success('Пользователь сохранён.');

        return to_route('platform.account.users.edit', ['user' => $user->id]);
    }
}
