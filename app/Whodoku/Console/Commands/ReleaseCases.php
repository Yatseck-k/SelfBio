<?php

declare(strict_types=1);

namespace Whodoku\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Whodoku\Application\Release\ReleaseRunner;
use Whodoku\Console\WhodokuCommand as Command;
use Whodoku\Models\ReleaseRun;

#[Description('Release of cases: fill the starting set, fill the reserve, publish today\'s batch or make the cases of the story')]
#[Signature('puzzles:release {kind : starter, reserve, daily or story} {--run= : Id of a run prepared by the admin button}')]
class ReleaseCases extends Command
{
    public function handle(ReleaseRunner $runner): int
    {
        $kind = (string) $this->argument('kind');
        if (!in_array($kind, ReleaseRunner::KINDS, true)) {
            $this->error('Kind must be one of: '.implode(', ', ReleaseRunner::KINDS));

            return self::INVALID;
        }

        $run = $this->option('run') !== null ? ReleaseRun::query()->findOrFail((int) $this->option('run')) : null;
        if ($run === null) {
            // Started by hand or by cron: make the row the admin screen shows.
            if ($runner->current() !== null) {
                $this->error('Another run is still going.');

                return self::FAILURE;
            }
            $run = ReleaseRun::query()->create(['kind' => $kind, 'status' => 'running', 'started_at' => now(), 'updated_at' => now()]);
        }

        $runner->execute($run);
        $run->refresh();
        $this->line("{$run->kind}: {$run->status}, создано {$run->created} из {$run->requested}, не удалось {$run->failed}".($run->message ? " — {$run->message}" : ''));

        return $run->status === 'done' ? self::SUCCESS : self::FAILURE;
    }
}
