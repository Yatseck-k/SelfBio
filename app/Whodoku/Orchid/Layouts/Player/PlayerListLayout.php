<?php

declare(strict_types=1);

namespace Whodoku\Orchid\Layouts\Player;

use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\DropDown;
use Orchid\Screen\Components\Cells\DateTimeSplit;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;
use Orchid\Support\Color;
use Whodoku\Application\Player\LevelLadder;
use Whodoku\Application\Player\PlayerSkills;
use Whodoku\Application\Player\RankLadder;
use Whodoku\Application\Player\SuspiciousPlay;
use Whodoku\Application\Story\StoryService;
use Whodoku\Models\Player;

class PlayerListLayout extends Table
{
    /** @var string */
    #[\Override]
    public $target = 'players';

    /** @return TD[] */
    public function columns(): array
    {
        return [
            TD::make('detective_name', 'Детектив')->cantHide()->sort()->render(fn (Player $player) => $player->detective_name ?? '— ещё не создан —'),
            TD::make('telegram', 'Telegram')->render(fn (Player $player) => $player->telegram_id < 0 ? 'тестовый вход' : ($player->telegram_username ? '@'.$player->telegram_username : (string) $player->first_name)),
            TD::make('points', 'Очки')->align(TD::ALIGN_RIGHT)->sort(),
            TD::make('rank', 'Звание')->render(fn (Player $player) => RankLadder::forChapters(resolve(StoryService::class)->solvedChapters($player))['title']),
            TD::make('level', 'Уровень')->align(TD::ALIGN_RIGHT)->render(fn (Player $player) => (string) LevelLadder::forPoints($player->points)['level']),
            TD::make('skills', 'Навыки (инт / вним / эксп)')->render(function (Player $player) {
                $points = (new PlayerSkills)->pointsOf($player);

                return "{$points['intellect']} / {$points['attention']} / {$points['expertise']}";
            }),
            TD::make('solved', 'Решено')->align(TD::ALIGN_RIGHT)->render(fn (Player $player) => (string) $player->solved_count),
            TD::make('flags', 'Проверить')->render(fn (Player $player) => ($reasons = (new SuspiciousPlay)->reasons($player)) === [] ? '—' : '⚠ '.implode('; ', $reasons)),
            TD::make('created_at', 'Регистрация')->usingComponent(DateTimeSplit::class)->sort(),
            TD::make('last_seen_at', 'Был')->usingComponent(DateTimeSplit::class),
            TD::make('actions', '')->align(TD::ALIGN_RIGHT)->cantHide()->width('4rem')->render(fn (Player $player) => DropDown::make()->icon('bs.three-dots-vertical')->list([
                Button::make('Сбросить навыки')->icon('bs.arrow-counterclockwise')
                    ->confirm('Очки развития игрока вернутся в запас, способности закроются. Продолжить?')->method('resetSkills', ['player' => $player->id]),
                $player->isBanned()
                    ? Button::make('Разблокировать')->icon('bs.unlock')->method('unban', ['player' => $player->id])
                    : Button::make('Заблокировать')->icon('bs.slash-circle')->type(Color::DANGER)
                        ->confirm('Игрок не сможет войти и пропадёт из рейтинга. Продолжить?')->method('ban', ['player' => $player->id]),
            ])),
        ];
    }
}
