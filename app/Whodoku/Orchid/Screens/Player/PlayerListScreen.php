<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Screens\Player;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Repository;
use Orchid\Screen\Screen;
use Orchid\Screen\Sight;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Whodoku\Application\Player\PlayAnalytics;
use Whodoku\Application\Player\PlayerSkills;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Player;
use Whodoku\Orchid\Layouts\Player\PlayerListLayout;

class PlayerListScreen extends Screen
{
    private const array SORTABLE = ['detective_name', 'points', 'created_at'];

    /** @return array<string, mixed> */
    public function query(Request $request): iterable
    {
        $sort = ltrim((string) $request->query('sort', '-created_at'), '-');
        $sort = in_array($sort, self::SORTABLE, true) ? $sort : 'created_at';

        return [
            'overall' => new Repository((new PlayAnalytics)->overall()),
            'players' => Player::query()
                ->withCount(['results as solved_count' => fn ($query) => $query->whereNotNull('solved_at')])
                ->orderBy($sort, str_starts_with((string) $request->query('sort', '-created_at'), '-') ? 'desc' : 'asc')
                ->paginate(),
        ];
    }

    public function name(): ?string
    {
        return 'Игроки';
    }

    public function description(): ?string
    {
        return 'Зарегистрированные через Telegram детективы: звание, очки, решённые дела.';
    }

    public function permission(): ?iterable
    {
        return ['platform.puzzles.players'];
    }

    public function layout(): iterable
    {
        return [
            Layout::legend('overall', [
                Sight::make('players', 'Игроков'),
                Sight::make('active_week', 'Заходили за неделю'),
                Sight::make('solved', 'Решено дел всего'),
                Sight::make('solved_week', 'Решено за неделю'),
            ])->title('Сводка'),
            Layout::table('players', (new PlayerListLayout)->columns()),
        ];
    }

    public function ban(Request $request, Player $player): RedirectResponse
    {
        return $this->setBanned($request, $player, true);
    }

    public function unban(Request $request, Player $player): RedirectResponse
    {
        return $this->setBanned($request, $player, false);
    }

    public function resetSkills(Request $request, Player $player, PlayerSkills $skills): RedirectResponse
    {
        abort_unless($request->user()->hasAccess('platform.puzzles.players'), 403);

        $skills->reset($player, byAdmin: true);
        AdminAuditLog::query()->create([
            'user_id' => $request->user()->id, 'actor_email' => $request->user()->email, 'action' => 'player_skills_reset',
            'validation_result' => ['player_id' => $player->id],
        ]);
        Toast::success('Навыки игрока сброшены: очки развития вернулись в запас.');

        return to_route('platform.players');
    }

    private function setBanned(Request $request, Player $player, bool $banned): RedirectResponse
    {
        abort_unless($request->user()->hasAccess('platform.puzzles.players'), 403);

        $player->update(['banned_at' => $banned ? now() : null]);
        AdminAuditLog::query()->create([
            'user_id' => $request->user()->id, 'actor_email' => $request->user()->email, 'action' => 'player_ban',
            'validation_result' => ['player_id' => $player->id, 'banned' => $banned],
        ]);
        Toast::success($banned ? 'Игрок заблокирован.' : 'Игрок разблокирован.');

        return to_route('platform.players');
    }
}
