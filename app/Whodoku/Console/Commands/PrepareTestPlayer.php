<?php

declare(strict_types=1);

namespace Whodoku\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\DB;
use Whodoku\Application\Player\LevelLadder;
use Whodoku\Application\Player\PlayerSkills;
use Whodoku\Console\WhodokuCommand as Command;
use Whodoku\Models\Player;
use Whodoku\Models\PlayerCaseResult;
use Whodoku\Models\Puzzle;

#[Description('Give a named test detective every published case, maximum experience and every skill; hide them from the leaderboard')]
#[Signature('players:prepare-test {name : Detective name, for example Yats}')]
class PrepareTestPlayer extends Command
{
    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $player = Player::query()->where('detective_key', mb_strtolower($name))->first();
        if ($player === null) {
            $this->error("Детектив «{$name}» не найден.");

            return self::FAILURE;
        }

        $result = DB::transaction(function () use ($player): array {
            $points = LevelLadder::pointsFor(LevelLadder::maxLevel());
            $skills = array_fill_keys(PlayerSkills::SKILLS, 99);
            $player->update([
                'points' => $points,
                'skills' => $skills,
                'hidden_from_leaderboard' => true,
            ]);

            $solved = 0;
            foreach (Puzzle::query()->where('status', 'published')->cursor() as $puzzle) {
                $case = PlayerCaseResult::query()->firstOrNew([
                    'player_id' => $player->id,
                    'puzzle_slug' => $puzzle->slug,
                    'revision' => $puzzle->revision,
                ]);
                $case->fill([
                    'puzzle_id' => $puzzle->id,
                    'puzzle_title' => $puzzle->title,
                    'puzzle_type' => $puzzle->type,
                    'difficulty' => $puzzle->difficulty,
                    'first_opened_at' => $case->first_opened_at ?? now(),
                    'solved_at' => $case->solved_at ?? now(),
                    'checks_count' => max(1, (int) $case->checks_count),
                    'hints_used' => 0,
                    'points_awarded' => max(0, (int) $case->points_awarded),
                    'duration_seconds' => $case->duration_seconds ?? 0,
                ]);
                $case->save();
                $solved++;
            }

            return [$points, $solved];
        });

        $this->info("{$player->detective_name}: {$result[1]} дел отмечено пройденными, {$result[0]} очков, профиль скрыт из рейтинга.");

        return self::SUCCESS;
    }
}
