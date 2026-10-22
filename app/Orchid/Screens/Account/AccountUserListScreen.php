<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Account;

use App\Models\User;
use App\Orchid\Layouts\AccountUserListLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

/** The people who can sign in to the personal account of the site (they are not the admins of this panel). */
class AccountUserListScreen extends Screen
{
    public function query(): iterable
    {
        return ['users' => User::query()->orderBy('name')->paginate(20)];
    }

    public function name(): ?string
    {
        return 'Пользователи личного кабинета';
    }

    public function description(): ?string
    {
        return 'Кто может войти в личный кабинет на сайте. Администраторы этой панели — отдельно, в разделе «Доступ к админке».';
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.account.view'];
    }

    public function commandBar(): iterable
    {
        return [Link::make('Новый пользователь')->icon('bs.plus-circle')->route('platform.account.users.create')
            ->canSee(request()->user()?->hasAccess('platform.selfbio.account.edit') ?? false)];
    }

    public function layout(): iterable
    {
        return [AccountUserListLayout::class];
    }

    public function remove(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.account.edit'), 403);
        User::query()->findOrFail($request->integer('user'))->delete();
        Toast::success('Пользователь удалён.');

        return to_route('platform.account.users');
    }
}
