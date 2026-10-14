<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

use Illuminate\Support\Facades\DB;
use Whodoku\Application\Story\StoryService;
use Whodoku\Models\Player;

/**
 * Development of the detective. Every level (see {@see LevelLadder}) gives one development point, and each solved chapter of the story a few more; the player puts
 * it into intellect, attention or expertise, and a skill opens abilities at the thresholds of `config/killdoku.php`.
 * Everything that gives an advantage is decided here, on the server.
 */
class PlayerSkills
{
    public const SKILLS = SkillCatalog::SKILLS;

    public function __construct(private readonly SkillCatalog $catalog = new SkillCatalog, private readonly ?StoryService $storyService = null) {}

    private function storyService(): StoryService
    {
        return $this->storyService ?? resolve(StoryService::class);
    }

    /** @return array<string, int> points put into each skill */
    public function pointsOf(?Player $player): array
    {
        $stored = $player->skills ?? [];

        return array_combine(self::SKILLS, array_map(fn (string $skill) => max(0, (int) ($stored[$skill] ?? 0)), self::SKILLS));
    }

    public function earned(?Player $player): int
    {
        return $player === null ? 0 : LevelLadder::earnedSkillPoints($player->points) + $this->storyService()->grantedTo($player);
    }

    public function spent(?Player $player): int
    {
        return array_sum($this->pointsOf($player));
    }

    public function free(?Player $player): int
    {
        return max(0, $this->earned($player) - $this->spent($player));
    }

    /**
     * What the player may use. A guest (null) has only the starting game: easiest cases, no notebook, no hints.
     *
     * @return array{difficulty: int, hints: bool, locate: bool, notes: bool, cover: bool, helper: bool, mark: bool, autoplace: bool, hint_stock: int, hint_pace: int, free_hints: int, points_bonus: int, hard_bonus: int}
     */
    public function abilities(?Player $player): array
    {
        // The catalog of cases asks for every case of the list: compute once per player and set of skills.
        $key = ($player?->id ?? 'guest').':'.json_encode($player?->skills);

        return $this->abilitiesCache[$key] ??= $this->computeAbilities($player);
    }

    /** @var array<string, array{difficulty: int, hints: bool, locate: bool, notes: bool, cover: bool, helper: bool, mark: bool, autoplace: bool, hint_stock: int, hint_pace: int, free_hints: int, points_bonus: int, hard_bonus: int}> */
    private array $abilitiesCache = [];

    /** @return array{difficulty: int, hints: bool, locate: bool, notes: bool, cover: bool, helper: bool, mark: bool, autoplace: bool, hint_stock: int, hint_pace: int, free_hints: int, points_bonus: int, hard_bonus: int} */
    private function computeAbilities(?Player $player): array
    {
        $points = $this->pointsOf($player);
        $open = [];
        foreach (self::SKILLS as $skill) {
            foreach ($this->catalog->steps($skill) as $step) {
                if ($points[$skill] >= $step['at']) {
                    $open[$step['ability']] = true;
                }
            }
        }

        $difficulty = 1;
        foreach ($open as $ability => $_) {
            if (preg_match('/^level_(\d+)$/', $ability, $found) === 1) {
                $difficulty = max($difficulty, (int) $found[1]);
            }
        }
        $firstHint = $this->threshold('hints');
        $stock = isset($open['hints']) ? intdiv($points['attention'] - $firstHint, max(1, (int) config('killdoku.skills.stock_step'))) : 0;
        // The deep steps of attention: a bigger reserve of hints and a hint for fewer solved cases.
        $stock += isset($open['stock_bonus_2']) ? 6 : (isset($open['stock_bonus_1']) ? 3 : 0);
        $pace = (int) config('killdoku.skills.solves_per_hint');
        if (isset($open['hint_pace_2'])) {
            $pace = max(1, $pace - 1);
        }

        return [
            'difficulty' => $difficulty,
            'hints' => isset($open['hints']),
            'locate' => isset($open['locate']),
            'notes' => isset($open['notes']),
            'cover' => isset($open['cover']),
            'helper' => isset($open['helper']),
            'mark' => isset($open['mark']),
            'autoplace' => isset($open['autoplace']),
            'hint_stock' => $stock,
            'hint_pace' => $pace,
            // Hint levels of a case that do not take the no-hints bonus away (attention).
            'free_hints' => isset($open['free_hints_3']) ? 3 : (isset($open['free_hints_2']) ? 2 : (isset($open['free_hints_1']) ? 1 : 0)),
            // Percent added to the points of every solved case (expertise).
            'points_bonus' => $this->steppedBonus($open, 'points_bonus', 5),
            // Percent added to the points of cases of difficulty 4–5 (intellect).
            'hard_bonus' => $this->steppedBonus($open, 'hard_bonus', 4),
        ];
    }

    /**
     * The percent of the highest opened step `<prefix>_1 … <prefix>_N`: ten per step.
     *
     * @param  array<string, bool>  $open
     */
    private function steppedBonus(array $open, string $prefix, int $steps): int
    {
        for ($step = $steps; $step >= 1; $step--) {
            if (isset($open["{$prefix}_{$step}"])) {
                return 10 * $step;
            }
        }

        return 0;
    }

    /** May this player open a case of the given difficulty? Guests have their own flagged cases and are not asked. */
    public function opensDifficulty(?Player $player, int $difficulty): bool
    {
        return !config('killdoku.skills.lock_levels', true) || $difficulty <= $this->abilities($player)['difficulty'];
    }

    /** The skill and the points that open the difficulty, for «Откроется при …». */
    public function unlockFor(int $difficulty): ?array
    {
        foreach ($this->catalog->steps('intellect') as $step) {
            if ($step['ability'] === "level_$difficulty") {
                return ['skill' => 'intellect', 'label' => config('killdoku.skills.intellect.label'), 'at' => $step['at']];
            }
        }

        return null;
    }

    /** Puts one development point into a skill. @return bool false when there is no free point or no such skill */
    public function allocate(Player $player, string $skill): bool
    {
        if (!in_array($skill, self::SKILLS, true)) {
            return false;
        }

        return DB::transaction(function () use ($player, $skill): bool {
            $fresh = Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();
            if ($this->free($fresh) < 1) {
                return false;
            }
            $points = $this->pointsOf($fresh);
            $points[$skill]++;
            $fresh->update(['skills' => $points]);
            $player->setRawAttributes($fresh->getAttributes(), true);

            return true;
        });
    }

    /**
     * Redistribution: the first time at once, then only after twenty more levels (`respec_levels`) since the last
     * one, so a choice keeps its weight.
     *
     * @return array{allowed: bool, next_level: ?int}
     */
    public function resetState(Player $player): array
    {
        if ($this->spent($player) === 0) {
            return ['allowed' => false, 'next_level' => null];
        }
        $last = $player->skills_reset_level;
        if ($last === null) {
            return ['allowed' => true, 'next_level' => null];
        }
        $next = $last + (int) config('killdoku.skills.respec_levels');
        $level = LevelLadder::forPoints($player->points)['level'];

        return $level >= $next ? ['allowed' => true, 'next_level' => null] : ['allowed' => false, 'next_level' => $next];
    }

    /** Takes all points back to be put again. @return bool false when it is not allowed now */
    public function reset(Player $player, bool $byAdmin = false): bool
    {
        if (!$byAdmin && !$this->resetState($player)['allowed']) {
            return false;
        }
        $player->update($byAdmin
            ? ['skills' => null]
            : ['skills' => null, 'skills_reset_at' => now(), 'skills_reset_level' => LevelLadder::forPoints($player->points)['level']]);

        return true;
    }

    /**
     * Skills for the profile: points, what each threshold opens and whether it is open already.
     *
     * @return array<string, mixed>
     */
    public function present(?Player $player): array
    {
        $points = $this->pointsOf($player);
        $skills = [];
        foreach (self::SKILLS as $skill) {
            $steps = array_map(fn (array $step) => [
                'at' => $step['at'], 'title' => $step['title'], 'open' => $points[$skill] >= $step['at'],
            ], $this->catalog->steps($skill));
            $next = collect($steps)->firstWhere('open', false);
            $skills[] = [
                'key' => $skill, 'label' => config("killdoku.skills.$skill.label"), 'about' => config("killdoku.skills.$skill.about"),
                'points' => $points[$skill], 'steps' => $steps, 'next_at' => $next['at'] ?? null,
            ];
        }

        return [
            'free' => $this->free($player),
            'earned' => $this->earned($player),
            'skills' => $skills,
            'reset' => $player === null ? ['allowed' => false, 'next_level' => null] : $this->resetState($player),
            'abilities' => $this->abilities($player),
        ];
    }

    private function threshold(string $ability): int
    {
        foreach (self::SKILLS as $skill) {
            foreach ($this->catalog->steps($skill) as $step) {
                if ($step['ability'] === $ability) {
                    return (int) $step['at'];
                }
            }
        }

        return 0;
    }
}
