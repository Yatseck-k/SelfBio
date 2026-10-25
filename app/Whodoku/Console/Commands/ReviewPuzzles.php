<?php

declare(strict_types=1);

namespace Whodoku\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Whodoku\Application\Puzzle\PublishOptions;
use Whodoku\Application\Puzzle\PuzzleReviewService;
use Whodoku\Console\WhodokuCommand as Command;
use Whodoku\Models\Puzzle;

#[Description('The review bot: solve cases by logic, play them through the public service and report problems')]
#[Signature('puzzles:review {slug? : Slug of one case} {--all : Review every case} {--record : Write the reports to the audit log} {--unpublish : Take a failed published case back to draft (story chapters are only reported)}')]
class ReviewPuzzles extends Command
{
    public function handle(PuzzleReviewService $service): int
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

        $failed = 0;
        foreach ($puzzles as $puzzle) {
            $report = $this->option('record') ? $service->reviewAndRecord($puzzle, null, ['console' => true]) : $service->review($puzzle);
            $status = $report->passed() ? '<info>OK</info>' : '<error>FAIL</error>';
            $this->line("{$status} {$puzzle->slug} — шагов: ".count($report->steps).", уровень: {$report->hardestLevel}");
            foreach ($report->failures() as $check) {
                $this->line("    ✗ {$check->title}".($check->message !== '' ? ": {$check->message}" : ''));
            }
            foreach ($report->warnings() as $check) {
                $this->line("    ! {$check->title}".($check->message !== '' ? ": {$check->message}" : ''));
            }
            if (!$report->passed() && $this->option('unpublish') && $puzzle->status === 'published' && $puzzle->collection !== PublishOptions::COLLECTION_STORY) {
                $puzzle->forceFill(['status' => 'draft'])->save();
                $this->line('    → снято с публикации (draft)');
            }
            $failed += $report->passed() ? 0 : 1;
        }

        $this->newLine();
        $this->line("Проверено дел: {$puzzles->count()}, не прошли: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
