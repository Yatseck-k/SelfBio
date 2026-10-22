<?php

declare(strict_types=1);

namespace App\Orchid\Screens;

use App\Support\PageTexts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

/** A screen for the headings and short explanations of some pages (see PageTexts). */
abstract class PageTextsScreen extends Screen
{
    /** @return list<string> the pages whose texts the screen edits («contacts», «blog», «puzzles») */
    abstract protected function pages(): array;

    /** The route the form returns to. */
    abstract protected function route(): string;

    public function query(): iterable
    {
        // The form fields are nested by dots: `texts.contacts.title` is PageTexts' `contacts.title`.
        $flat = PageTexts::flat();
        $texts = [];
        foreach (array_keys($this->fields()) as $field) {
            data_set($texts, $field, $flat[$field]);
        }

        return ['texts' => $texts];
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.settings.view'];
    }

    public function commandBar(): iterable
    {
        return [Button::make('Сохранить')->icon('bs.check-circle')->method('save')
            ->canSee(request()->user()?->hasAccess('platform.selfbio.settings.edit') ?? false)];
    }

    public function layout(): iterable
    {
        $fields = [];
        foreach ($this->fields() as $field => $meta) {
            $input = $meta['long'] ? TextArea::make("texts.{$field}")->rows(4) : Input::make("texts.{$field}");
            $fields[] = $input->title($meta['label'])->maxlength(1000)
                ->help($meta['default'] === '' ? 'Если оставить пустым, пояснения не будет.' : 'Если оставить пустым или стереть, вернётся текст по умолчанию.');
        }

        return [Layout::rows($fields)];
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.selfbio.settings.edit'), 403);
        $data = $request->validate(['texts' => ['nullable', 'array'], 'texts.*.*' => ['nullable', 'string', 'max:1000']]);

        $values = [];
        foreach ((array) ($data['texts'] ?? []) as $page => $fields) {
            foreach ((array) $fields as $field => $value) {
                // Only the texts of this screen can be changed through it.
                if (array_key_exists("{$page}.{$field}", $this->fields())) {
                    $values["{$page}.{$field}"] = $value;
                }
            }
        }
        PageTexts::save($values);
        Toast::success('Тексты сохранены.');

        return to_route($this->route());
    }

    /** @return array<string, array{group: string, label: string, default: string, long: bool}> */
    private function fields(): array
    {
        return array_filter(PageTexts::FIELDS, fn (array $meta): bool => in_array($meta['group'], $this->pages(), true));
    }
}
