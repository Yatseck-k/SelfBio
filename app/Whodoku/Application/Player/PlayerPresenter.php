<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

use Whodoku\Application\Story\StoryService;
use Whodoku\Models\Player;

/** What the browser may know about a signed-in player. */
class PlayerPresenter
{
    public function __construct(private readonly PlayerProgress $progress = new PlayerProgress, private readonly PlayerSkills $skills = new PlayerSkills) {}

    /** @return array<string, mixed> */
    public function present(Player $player): array
    {
        $chapters = resolve(StoryService::class)->solvedChapters($player);

        return [
            'id' => $player->id,
            'first_name' => $player->first_name,
            'telegram_username' => $player->telegram_username,
            'photo_url' => $player->photo_url,
            'detective' => $player->detective_name === null ? null : ['name' => $player->detective_name, 'avatar' => $player->avatar],
            'points' => $player->points,
            'rank' => RankLadder::forChapters($chapters),
            'chapters' => $chapters,
            'level' => LevelLadder::forPoints($player->points),
            'development' => $this->skills->present($player),
            'solved' => $this->progress->solvedCount($player),
            'hints' => $this->progress->hints($player),
        ];
    }
}
