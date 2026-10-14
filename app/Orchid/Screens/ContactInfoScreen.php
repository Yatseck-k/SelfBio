<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Models\ContactInfo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ContactInfoScreen extends Screen
{
    public function query(): iterable
    {
        $contact = ContactInfo::query()->first();

        return ['contact' => $contact?->toArray() ?? []];
    }

    public function name(): ?string
    {
        return 'Контакты SelfBio';
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.contacts.view'];
    }

    public function commandBar(): iterable
    {
        return [Button::make('Сохранить')->icon('bs.check-circle')->method('save')
            ->canSee(request()->user()?->hasAccess('platform.selfbio.contacts.edit') ?? false)];
    }

    public function layout(): iterable
    {
        return [Layout::rows([
            Input::make('contact.name')->title('Имя')->required(),
            Input::make('contact.phone')->title('Телефон'),
            Input::make('contact.email')->title('Email')->type('email')->required(),
            Input::make('contact.telegram')->title('Telegram'),
            Input::make('contact.github')->title('GitHub'),
            TextArea::make('contact.socials')->title('Социальные сети (JSON)')->rows(6)
                ->value(json_encode((array) (ContactInfo::query()->first()?->socials ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
        ])];
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.contacts.edit'), 403);
        $data = $request->validate([
            'contact.name' => ['required', 'string', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.email' => ['required', 'email'],
            'contact.telegram' => ['nullable', 'string', 'max:100'],
            'contact.github' => ['nullable', 'string', 'max:100'],
            'contact.socials' => ['nullable', 'json'],
        ])['contact'];

        $data['socials'] = json_decode((string) ($data['socials'] ?? '[]'), true);
        if (!is_array($data['socials']) || !array_is_list($data['socials'])) {
            throw ValidationException::withMessages(['contact.socials' => 'Укажите массив социальных сетей в формате JSON.']);
        }
        $contact = ContactInfo::query()->first() ?? new ContactInfo;
        $contact->fill($data)->save();
        Cache::forget('contact.show.data');
        Toast::success('Контакты сохранены.');

        return to_route('platform.selfbio.contacts');
    }
}
