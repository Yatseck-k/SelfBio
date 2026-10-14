<?php

declare(strict_types=1);

namespace Whodoku\Application\Player;

use Whodoku\Application\Settings\GameSettings;

/**
 * What each skill opens and from how many points. The abilities and their titles are fixed in
 * `config/killdoku.php`; the thresholds can be changed by the administrator («Навыки»), and the changed ones are
 * kept in the settings table.
 */
class SkillCatalog
{
    public const KEY = 'skills.thresholds';

    public const SKILLS = ['intellect', 'attention', 'expertise'];

    public function __construct(private readonly GameSettings $settings = new GameSettings) {}

    /** @return list<array{at: int, ability: string, title: string}> */
    public function steps(string $skill): array
    {
        $overrides = $this->settings->get(self::KEY) ?? [];

        return array_map(function (array $step) use ($overrides): array {
            $at = $overrides[$step['ability']] ?? $step['at'];

            return ['at' => (int) $at, 'ability' => $step['ability'], 'title' => $step['title']];
        }, config("killdoku.skills.$skill.steps"));
    }

    /** @return array<string, int> ability => points, as the config has them */
    public function defaults(): array
    {
        $defaults = [];
        foreach (self::SKILLS as $skill) {
            foreach (config("killdoku.skills.$skill.steps") as $step) {
                $defaults[$step['ability']] = (int) $step['at'];
            }
        }

        return $defaults;
    }

    /** @return array<string, int> ability => points now */
    public function current(): array
    {
        $now = [];
        foreach (self::SKILLS as $skill) {
            foreach ($this->steps($skill) as $step) {
                $now[$step['ability']] = $step['at'];
            }
        }

        return $now;
    }

    /**
     * Thresholds must be whole numbers from 1 to 99 and grow inside a skill in the order the abilities are listed.
     *
     * @param  array<string, mixed>  $input  ability => points
     * @return list<string> what is wrong; empty when the input can be saved
     */
    public function problems(array $input): array
    {
        $problems = [];
        foreach (self::SKILLS as $skill) {
            $previous = 0;
            foreach (config("killdoku.skills.$skill.steps") as $step) {
                $raw = $input[$step['ability']] ?? null;
                if (!is_numeric($raw) || (int) $raw != $raw || (int) $raw < 1 || (int) $raw > 99) {
                    $problems[] = "«{$step['title']}»: нужно целое число от 1 до 99.";

                    continue;
                }
                if ((int) $raw <= $previous) {
                    $problems[] = "«{$step['title']}»: порог должен быть больше предыдущего в навыке «".config("killdoku.skills.$skill.label").'» (больше '.$previous.').';
                }
                $previous = (int) $raw;
            }
        }

        return $problems;
    }

    /** @param array<string, mixed> $input */
    public function save(array $input): void
    {
        $values = [];
        foreach ($this->defaults() as $ability => $default) {
            $values[$ability] = (int) $input[$ability];
        }
        // Only what differs from the config is stored, so a changed default in the config still reaches the others.
        $changed = array_filter($values, fn (int $at, string $ability) => $at !== $this->defaults()[$ability], ARRAY_FILTER_USE_BOTH);
        $changed === [] ? $this->settings->forget(self::KEY) : $this->settings->put(self::KEY, $changed);
    }

    public function reset(): void
    {
        $this->settings->forget(self::KEY);
    }
}
