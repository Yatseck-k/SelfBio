<?php

declare(strict_types=1);

namespace App\Orchid\Layouts;

use App\Models\User;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class AccountUserListLayout extends Table
{
    #[\Override]
    public $target = 'users';

    public function columns(): array
    {
        $canEdit = request()->user()?->hasAccess('platform.selfbio.account.edit') ?? false;

        return [
            TD::make('name', 'Имя')->render(fn (User $user) => Link::make($user->name)->route('platform.account.users.edit', ['user' => $user->id])),
            TD::make('email', 'Email'),
            TD::make('created_at', 'Создан')->render(fn (User $user): string => $user->created_at?->copy()->timezone(config('app.display_timezone'))->format('d.m.Y H:i') ?? '—'),
            TD::make('actions', '')->render(fn (User $user) => Button::make('Удалить')->icon('bs.trash3')
                ->confirm('Удалить пользователя «'.$user->email.'»? Он больше не сможет войти в личный кабинет.')
                ->method('remove', ['user' => $user->id])->canSee($canEdit)),
        ];
    }
}
