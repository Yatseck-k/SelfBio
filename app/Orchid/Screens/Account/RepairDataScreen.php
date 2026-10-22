<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Account;

use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;

/** The «Ремонт» tab of the personal account, seen from the admin panel: what the catalog holds (read only). */
class RepairDataScreen extends Screen
{
    /** @var array{sections: list<array{name: string, messages: int, files: int}>, messages: int, files: int, ready: bool} */
    private array $summary = ['sections' => [], 'messages' => 0, 'files' => 0, 'ready' => false];

    public function query(): iterable
    {
        $this->summary = $this->summarize();

        return ['sections' => $this->summary['sections']];
    }

    public function name(): ?string
    {
        return 'Ремонт';
    }

    public function description(): ?string
    {
        return $this->summary['ready']
            ? "Вкладка «Ремонт» личного кабинета. В каталоге {$this->summary['messages']} сообщений и {$this->summary['files']} вложений. Менять каталог можно только в самой вкладке."
            : 'Вкладка «Ремонт» личного кабинета. Каталог пока пуст: материалы кладут в закрытый том (REPAIR_ROOT).';
    }

    public function permission(): ?iterable
    {
        return ['platform.selfbio.account.view'];
    }

    public function layout(): iterable
    {
        return [Layout::table('sections', [
            TD::make('name', 'Раздел')->render(fn ($row): string => e($row['name'])),
            TD::make('messages', 'Сообщений')->alignRight()->render(fn ($row): string => (string) $row['messages']),
            TD::make('files', 'Вложений')->alignRight()->render(fn ($row): string => (string) $row['files']),
        ])];
    }

    /** @return array{sections: list<array{name: string, messages: int, files: int}>, messages: int, files: int, ready: bool} */
    private function summarize(): array
    {
        $path = rtrim((string) config('services.repair.root'), '/').'/catalog.json';
        $catalog = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($catalog)) {
            return ['sections' => [], 'messages' => 0, 'files' => 0, 'ready' => false];
        }
        $locale = resource_path('repair/locales/ru.json');
        $names = is_file($locale) ? (json_decode((string) file_get_contents($locale), true)['sections'] ?? []) : [];

        $sections = [];
        $messages = $files = 0;
        foreach ((array) ($catalog['sections'] ?? []) as $section) {
            $count = count($section['messages'] ?? []);
            $attached = array_sum(array_map(fn (array $message): int => count($message['attachments'] ?? []) + count($message['images'] ?? []), $section['messages'] ?? []));
            $id = (string) ($section['id'] ?? '');
            $sections[] = ['name' => (string) ($names[$id]['name'] ?? ($id !== '' ? $id : '—')), 'messages' => $count, 'files' => $attached];
            $messages += $count;
            $files += $attached;
        }

        return ['sections' => $sections, 'messages' => $messages, 'files' => $files, 'ready' => $messages > 0];
    }
}
