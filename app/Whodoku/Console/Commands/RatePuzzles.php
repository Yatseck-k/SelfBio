<?php

declare(strict_types=1);

namespace Whodoku\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Whodoku\Application\Puzzle\PuzzleRatingService;
use Whodoku\Console\WhodokuCommand as Command;
use Whodoku\Models\Puzzle;

#[Description('Measure cases (logic, clarity, size, conditions) and store the difficulty rating of the current model')]
#[Signature('puzzles:rate {slug? : Slug of one case} {--all : Rate every case} {--dry : Only show the measurements, store nothing}')]
class RatePuzzles extends Command
{
    public function handle(PuzzleRatingService $service): int
    {
        $slug = $this->argument('slug');
        if ($slug === null && !$this->option('all')) {
            $this->error('Give a slug or use --all.');

            return self::INVALID;
        }

        $puzzles = Puzzle::query()->when($slug !== null, fn ($query) => $query->where('slug', $slug))->orderBy('id')->get();
        if ($puzzles->isEmpty()) {
            $this->error('No such case.');

            return self::FAILURE;
        }

        $rows = [];
        foreach ($puzzles as $puzzle) {
            $result = $service->measure($puzzle);
            $before = $puzzle->difficulty;
            if (!$this->option('dry')) {
                $puzzle->update(['difficulty' => $result['difficulty'], 'metrics' => $result['metrics']->toArray()]);
            }
            $parts = $result['parts'];
            $rows[] = [
                $puzzle->slug, "{$puzzle->rows}×{$puzzle->cols}", $before, $result['difficulty'], round($result['score'], 2),
                round($parts['logic'], 2), round($parts['clarity'], 2), round($parts['size'], 2), round($parts['conditions'], 2),
            ];
        }

        $this->table(['Дело', 'Карта', 'Было', 'Стало', 'Балл', 'Логика', 'Ясность', 'Размер', 'Условия'], $rows);
        $this->line(($this->option('dry') ? 'Измерено (без записи): ' : 'Оценено: ').count($rows));

        return self::SUCCESS;
    }
}
