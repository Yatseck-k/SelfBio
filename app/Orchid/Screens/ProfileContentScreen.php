<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Support\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Field;
use Orchid\Screen\Fields\CheckBox;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Matrix;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class ProfileContentScreen extends Screen
{
    public function query(): iterable
    {
        return ['profile' => [
            'ru' => Profile::editor('ru'),
            'en' => Profile::editor('en'),
        ]];
    }

    public function name(): ?string
    {
        return 'Главная и «Обо мне»';
    }

    public function description(): ?string
    {
        return 'Тексты и блоки профессионального профиля на русском и английском языках. Контакты и статьи редактируются отдельно.';
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.settings.view'];
    }

    public function commandBar(): iterable
    {
        $canEdit = request()->user()?->hasAccess('platform.selfbio.settings.edit') ?? false;

        return [
            Button::make('Сохранить оба языка')->icon('bs.check-circle')->method('save')->canSee($canEdit),
            Button::make('Сбросить RU')->icon('bs.arrow-counterclockwise')->method('reset', ['locale' => 'ru'])
                ->confirm('Вернуть русские тексты по умолчанию?')->canSee($canEdit),
            Button::make('Сбросить EN')->icon('bs.arrow-counterclockwise')->method('reset', ['locale' => 'en'])
                ->confirm('Вернуть английские тексты по умолчанию?')->canSee($canEdit),
        ];
    }

    public function layout(): iterable
    {
        return [Layout::tabs([
            'Русский' => Layout::rows($this->fields('ru')),
            'English' => Layout::rows($this->fields('en')),
        ])];
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.settings.edit'), 403);

        $rules = [];
        foreach (['ru', 'en'] as $locale) {
            $prefix = "profile.{$locale}.";
            $rules[$prefix.'name'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'role'] = ['required', 'string', 'max:120'];
            $rules[$prefix.'lead'] = ['required', 'string', 'max:600'];
            $rules[$prefix.'hero_eyebrow'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'hero_tags'] = ['nullable', 'string', 'max:500'];
            $rules[$prefix.'about_title'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'about_eyebrow'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'about_lead'] = ['required', 'string', 'max:600'];
            $rules[$prefix.'about_button'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'contact_button'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'about_text'] = ['required', 'string', 'max:5000'];
            $rules[$prefix.'focus_enabled'] = ['required', 'boolean'];
            $rules[$prefix.'focus_order'] = ['required', 'integer', 'between:1,4'];
            $rules[$prefix.'focus_eyebrow'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'focus_title'] = ['required', 'string', 'max:120'];
            $rules[$prefix.'focus'] = ['sometimes', 'array', 'max:12'];
            $rules[$prefix.'focus.*.title'] = ['required', 'string', 'max:120'];
            $rules[$prefix.'focus.*.text'] = ['required', 'string', 'max:500'];
            $rules[$prefix.'stack_enabled'] = ['required', 'boolean'];
            $rules[$prefix.'stack_order'] = ['required', 'integer', 'between:1,4'];
            $rules[$prefix.'stack_eyebrow'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'stack_title'] = ['required', 'string', 'max:120'];
            $rules[$prefix.'stack'] = ['sometimes', 'array', 'max:8'];
            $rules[$prefix.'stack.*.title'] = ['required', 'string', 'max:120'];
            $rules[$prefix.'stack.*.items'] = ['required', 'string', 'max:500'];
            $rules[$prefix.'writing_enabled'] = ['required', 'boolean'];
            $rules[$prefix.'writing_order'] = ['required', 'integer', 'between:1,4'];
            $rules[$prefix.'writing_eyebrow'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'writing_title'] = ['required', 'string', 'max:120'];
            $rules[$prefix.'articles_button'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'contact_enabled'] = ['required', 'boolean'];
            $rules[$prefix.'contact_order'] = ['required', 'integer', 'between:1,4'];
            $rules[$prefix.'contact_eyebrow'] = ['required', 'string', 'max:100'];
            $rules[$prefix.'contact_title'] = ['required', 'string', 'max:120'];
            $rules[$prefix.'contact_text'] = ['required', 'string', 'max:500'];
        }

        $validated = $request->validate($rules)['profile'];
        foreach (['ru', 'en'] as $locale) {
            $orders = array_map(fn (string $section): int => (int) $validated[$locale][$section.'_order'], ['focus', 'stack', 'writing', 'contact']);
            if (count(array_unique($orders)) !== 4) {
                throw ValidationException::withMessages(["profile.{$locale}.focus_order" => 'У каждого блока должен быть свой номер от 1 до 4.']);
            }
        }
        DB::connection('pgsql')->transaction(function () use ($validated): void {
            foreach (['ru', 'en'] as $locale) {
                $content = $validated[$locale];
                $content['hero_tags'] = (string) ($content['hero_tags'] ?? '');
                $content['focus'] = array_values($content['focus'] ?? []);
                $content['stack'] = array_values($content['stack'] ?? []);
                foreach (['focus_enabled', 'stack_enabled', 'writing_enabled', 'contact_enabled'] as $field) {
                    $content[$field] = (bool) $content[$field];
                }
                foreach (['focus_order', 'stack_order', 'writing_order', 'contact_order'] as $field) {
                    $content[$field] = (int) $content[$field];
                }
                Profile::save($locale, $content);
            }
        });
        Toast::success('Профиль обновлён на обоих языках.');

        return to_route('platform.selfbio.profile');
    }

    public function reset(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.settings.edit'), 403);
        $locale = $request->validate(['locale' => ['required', 'in:ru,en']])['locale'];
        Profile::reset($locale);
        Toast::success('Тексты восстановлены.');

        return to_route('platform.selfbio.profile');
    }

    /** @return array<int, Field> */
    private function fields(string $locale): array
    {
        $field = fn (string $name): string => "profile.{$locale}.{$name}";

        return [
            Input::make($field('name'))->title('Имя')->required()->maxlength(100),
            Input::make($field('role'))->title('Специализация')->required()->maxlength(120),
            TextArea::make($field('lead'))->title('Краткое представление')->required()->rows(3)->maxlength(600),
            Input::make($field('hero_eyebrow'))->title('Первый экран: подпись')->required()->maxlength(100),
            Input::make($field('hero_tags'))->title('Первый экран: технологии через запятую')->maxlength(500),
            Input::make($field('about_button'))->title('Кнопка «Обо мне»')->required()->maxlength(100),
            Input::make($field('contact_button'))->title('Кнопка контактов')->required()->maxlength(100),
            Input::make($field('about_title'))->title('Страница «Обо мне»: заголовок')->required()->maxlength(100),
            Input::make($field('about_eyebrow'))->title('Страница «Обо мне»: подпись')->required()->maxlength(100),
            TextArea::make($field('about_lead'))->title('Страница «Обо мне»: вступление')->required()->rows(3)->maxlength(600),
            TextArea::make($field('about_text'))->title('Страница «Обо мне»: абзацы')->required()->rows(8)->maxlength(5000)
                ->help('Разделяйте абзацы пустой строкой.'),
            CheckBox::make($field('focus_enabled'))->title('Показывать блок компетенций')->sendTrueOrFalse(),
            Input::make($field('focus_order'))->title('Компетенции: порядок (1–4)')->type('number')->min(1)->max(4)->required(),
            Input::make($field('focus_eyebrow'))->title('Компетенции: подпись')->required()->maxlength(100)->help('Номер добавляется автоматически.'),
            Input::make($field('focus_title'))->title('Компетенции: заголовок')->required()->maxlength(120),
            Matrix::make($field('focus'))->title('Карточки компетенций')->columns(['Название' => 'title', 'Описание' => 'text'])
                ->maxRows(12)->addRowLabel('Добавить компетенцию'),
            CheckBox::make($field('stack_enabled'))->title('Показывать блок технологий')->sendTrueOrFalse(),
            Input::make($field('stack_order'))->title('Технологии: порядок (1–4)')->type('number')->min(1)->max(4)->required(),
            Input::make($field('stack_eyebrow'))->title('Технологии: подпись')->required()->maxlength(100)->help('Номер добавляется автоматически.'),
            Input::make($field('stack_title'))->title('Технологии: заголовок')->required()->maxlength(120),
            Matrix::make($field('stack'))->title('Группы технологий')->columns(['Группа' => 'title', 'Технологии через запятую' => 'items'])
                ->maxRows(8)->addRowLabel('Добавить группу'),
            CheckBox::make($field('writing_enabled'))->title('Показывать последние статьи')->sendTrueOrFalse(),
            Input::make($field('writing_order'))->title('Статьи: порядок (1–4)')->type('number')->min(1)->max(4)->required(),
            Input::make($field('writing_eyebrow'))->title('Статьи: подпись')->required()->maxlength(100)->help('Номер добавляется автоматически.'),
            Input::make($field('writing_title'))->title('Статьи: заголовок')->required()->maxlength(120),
            Input::make($field('articles_button'))->title('Ссылка на все статьи')->required()->maxlength(100),
            CheckBox::make($field('contact_enabled'))->title('Показывать приглашение связаться')->sendTrueOrFalse(),
            Input::make($field('contact_order'))->title('Контакты: порядок (1–4)')->type('number')->min(1)->max(4)->required(),
            Input::make($field('contact_eyebrow'))->title('Контакты: подпись')->required()->maxlength(100)->help('Номер добавляется автоматически.'),
            Input::make($field('contact_title'))->title('Контакты: заголовок')->required()->maxlength(120),
            TextArea::make($field('contact_text'))->title('Контакты: текст')->required()->rows(3)->maxlength(500),
        ];
    }
}
