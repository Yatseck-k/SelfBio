<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Skills;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Screen;
use Orchid\Support\Color;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Player\SkillCatalog;
use Whodoku\Models\AdminAuditLog;

/** How many points of a skill open each ability (task 45); the abilities themselves are fixed in the config. */
class SkillSettingsScreen extends Screen
{
    public function __construct(private readonly SkillCatalog $catalog) {}

    /** @return array<string, mixed> */
    public function query(): iterable
    {
        return ['at' => $this->catalog->current()];
    }

    public function name(): ?string
    {
        return 'Навыки детектива';
    }

    public function description(): ?string
    {
        return 'Сколько очков навыка открывают возможности. Новые значения действуют сразу; уже вложенные очки игроков не меняются.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.players'];
    }

    /** @return Action[] */
    public function commandBar(): iterable
    {
        return [
            Button::make('Вернуть значения по умолчанию')->icon('bs.arrow-counterclockwise')->type(Color::DANGER)->method('reset')
                ->confirm('Все пороги вернутся к значениям из конфигурации. Продолжить?'),
            Button::make('Сохранить')->icon('bs.check-circle')->method('save'),
        ];
    }

    public function layout(): iterable
    {
        $blocks = [Layout::view('orchid.skills.notes')];
        foreach (SkillCatalog::SKILLS as $skill) {
            $fields = [];
            foreach ($this->catalog->steps($skill) as $step) {
                $fields[] = Input::make("at.{$step['ability']}")->type('number')->min(1)->max(99)->required()
                    ->title($step['title'])->help("По умолчанию: {$this->catalog->defaults()[$step['ability']]}");
            }
            $blocks[] = Layout::block(Layout::rows($fields))
                ->title((string) config("killdoku.skills.$skill.label"))
                ->description((string) config("killdoku.skills.$skill.about"));
        }

        return $blocks;
    }

    public function save(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.puzzles.players'), 403);

        $input = (array) $request->input('at', []);
        $problems = $this->catalog->problems($input);
        if ($problems !== []) {
            Toast::error(implode(' ', $problems))->persistent();

            return to_route('platform.skills');
        }

        $before = $this->catalog->current();
        $this->catalog->save($input);
        $this->audit($request, 'skill_settings', ['before' => $before, 'after' => $this->catalog->current()]);
        Toast::success('Пороги сохранены.');

        return to_route('platform.skills');
    }

    public function reset(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAccess('platform.puzzles.players'), 403);

        $this->catalog->reset();
        $this->audit($request, 'skill_settings', ['reset' => true]);
        Toast::success('Пороги возвращены к значениям по умолчанию.');

        return to_route('platform.skills');
    }

    /** @param array<string, mixed> $details */
    private function audit(Request $request, string $action, array $details): void
    {
        AdminAuditLog::query()->create(['user_id' => $request->user()?->id, 'actor_email' => $request->user()?->email, 'action' => $action, 'validation_result' => $details]);
    }
}
