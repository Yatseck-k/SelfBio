<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Incident;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Screen;
use Orchid\Screen\TD;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Puzzle\PuzzleReviewService;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Incident;

/** Reports of players about cases: look at them from time to time, run the bot on the case, fix it, close the report. */
class IncidentListScreen extends Screen
{
    private const OPEN = 'open';

    /** @return array<string, mixed> */
    public function query(Request $request): iterable
    {
        $status = (string) $request->query('status', self::OPEN);
        $category = (string) $request->query('category', '');

        return [
            'incidents' => Incident::query()->with(['puzzle', 'player'])
                ->when($status === self::OPEN, fn ($query) => $query->whereIn('status', [Incident::NEW, Incident::IN_PROGRESS]))
                ->when(isset(Incident::STATUSES[$status]), fn ($query) => $query->where('status', $status))
                ->when(isset(Incident::CATEGORIES[$category]), fn ($query) => $query->where('category', $category))
                ->latest('id')->paginate(),
        ];
    }

    public function name(): ?string
    {
        return 'Обращения игроков';
    }

    public function description(): ?string
    {
        return 'Сообщения «Сообщить об ошибке»: перепроверьте дело ботом, исправьте и закройте обращение.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.validate'];
    }

    public function layout(): iterable
    {
        return [
            Layout::rows([
                Select::make('status')->options([self::OPEN => 'Открытые (новые и в работе)', 'all' => 'Все'] + Incident::STATUSES)->title('Статус')->value(request()->query('status', self::OPEN)),
                Select::make('category')->options(['' => 'Любая'] + Incident::CATEGORIES)->title('Что не так')->value(request()->query('category', '')),
                Button::make('Показать')->method('filter')->icon('bs.funnel'),
            ]),
            Layout::table('incidents', [
                TD::make('id', '№')->render(fn (Incident $incident) => (string) $incident->id),
                TD::make('created_at', 'Когда')->usingComponent(DateTimeSplit::class),
                TD::make('puzzle', 'Дело')->render(fn (Incident $incident) => $incident->puzzle !== null
                    ? Link::make($incident->puzzle->title)->route('platform.puzzles.show', $incident->puzzle)->render()
                    : e($incident->puzzle_slug).' (дело заменено или удалено)'),
                TD::make('category', 'Что не так')->render(fn (Incident $incident) => Incident::CATEGORIES[$incident->category] ?? $incident->category),
                TD::make('message', 'Сообщение')->width('24rem')->render(fn (Incident $incident) => e($incident->message)
                    .($incident->resolution !== null ? '<br><small class="text-muted">Ответ: '.e($incident->resolution).'</small>' : '')),
                TD::make('player', 'Игрок')->render(fn (Incident $incident) => $incident->player->detective_name ?? 'гость'),
                TD::make('status', 'Статус')->render(fn (Incident $incident) => Incident::STATUSES[$incident->status] ?? $incident->status),
                TD::make('actions', '')->align(TD::ALIGN_RIGHT)->cantHide()->width('4rem')->render(fn (Incident $incident) => DropDown::make()->icon('bs.three-dots-vertical')->list([
                    Button::make('Проверить дело ботом')->icon('bs.robot')->method('review', ['incident' => $incident->id])
                        ->canSee($incident->puzzle !== null),
                    Button::make('Взять в работу')->icon('bs.play')->method('start', ['incident' => $incident->id])
                        ->canSee($incident->status === Incident::NEW),
                    Button::make('Решено')->icon('bs.check2-circle')->method('resolve', ['incident' => $incident->id])
                        ->canSee(in_array($incident->status, [Incident::NEW, Incident::IN_PROGRESS], true)),
                    Button::make('Отклонить')->icon('bs.x-circle')->method('reject', ['incident' => $incident->id])
                        ->confirm('Обращение будет закрыто без исправления дела. Продолжить?')
                        ->canSee(in_array($incident->status, [Incident::NEW, Incident::IN_PROGRESS], true)),
                    Button::make('Вернуть в работу')->icon('bs.arrow-counterclockwise')->method('start', ['incident' => $incident->id])
                        ->canSee(in_array($incident->status, [Incident::RESOLVED, Incident::REJECTED], true)),
                ])),
            ]),
            Layout::rows([
                Input::make('note')->title('Ответ или заметка к закрытию')->help('Появится в строке обращения. Заполните перед кнопкой «Решено» или «Отклонить».'),
            ]),
        ];
    }

    public function filter(Request $request): RedirectResponse
    {
        return to_route('platform.incidents', array_filter([
            'status' => $request->input('status'), 'category' => $request->input('category'),
        ]));
    }

    public function start(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorizeAction($request);
        $incident->forceFill(['status' => Incident::IN_PROGRESS, 'resolved_at' => null, 'resolved_by' => null])->save();
        $this->audit($request, 'incident_start', $incident);
        Toast::info('Обращение взято в работу.');

        return back();
    }

    public function resolve(Request $request, Incident $incident): RedirectResponse
    {
        return $this->close($request, $incident, Incident::RESOLVED, 'Обращение закрыто как решённое.');
    }

    public function reject(Request $request, Incident $incident): RedirectResponse
    {
        return $this->close($request, $incident, Incident::REJECTED, 'Обращение отклонено.');
    }

    public function review(Request $request, Incident $incident, PuzzleReviewService $service): RedirectResponse
    {
        $this->authorizeAction($request);
        abort_if($incident->puzzle === null, 404);

        $report = $service->reviewAndRecord($incident->puzzle, $request->user(), ['incident' => $incident->id]);
        $report->passed()
            ? Toast::success('Бот прошёл дело: решаемо логикой, всё понятно. Если игрок прав, причина в интерфейсе или в формулировке.')
            : Toast::error('Бот нашёл проблемы: '.implode('; ', array_map(fn ($check) => $check->title, $report->failures())));

        return back();
    }

    private function close(Request $request, Incident $incident, string $status, string $message): RedirectResponse
    {
        $this->authorizeAction($request);
        $note = trim((string) $request->input('note'));
        $incident->forceFill([
            'status' => $status, 'resolution' => $note !== '' ? mb_substr($note, 0, 500) : $incident->resolution,
            'resolved_by' => $request->user()->id, 'resolved_at' => now(),
        ])->save();
        $this->audit($request, "incident_{$status}", $incident);
        Toast::success($message);

        return back();
    }

    private function authorizeAction(Request $request): void
    {
        abort_unless($request->user()?->hasAccess('platform.puzzles.validate'), 403);
    }

    private function audit(Request $request, string $action, Incident $incident): void
    {
        AdminAuditLog::query()->create([
            'user_id' => $request->user()->id, 'actor_email' => $request->user()->email, 'puzzle_id' => $incident->puzzle_id,
            'puzzle_slug' => $incident->puzzle_slug, 'action' => $action, 'validation_result' => ['incident_id' => $incident->id],
        ]);
    }
}
