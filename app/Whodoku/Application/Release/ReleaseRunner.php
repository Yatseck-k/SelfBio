<?php

declare(strict_types=1);

namespace Whodoku\Application\Release;

use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;
use Throwable;
use Whodoku\Models\ReleaseRun;
use Whodoku\Models\User;

/**
 * Starts the release tool from the admin button. Generating a hundred cases takes minutes, so the work runs in a
 * separate PHP process (`php artisan puzzles:release`) and the screen shows the progress from the `release_runs`
 * table. Only one run goes at a time. In tests and with `KILLDOKU_RELEASE_INLINE=true` it runs in place.
 */
class ReleaseRunner
{
    public const KINDS = ['starter', 'reserve', 'daily', 'story'];

    /** A run that has not moved for this long is considered dead (its process was stopped). */
    private const int STALE_MINUTES = 20;

    public function __construct(private readonly ReleaseService $service = new ReleaseService) {}

    /** @throws ReleaseBusyException when another run is still going */
    public function start(string $kind, ?User $actor = null): ReleaseRun
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException("Unknown release kind $kind.");
        }
        if ($this->current() !== null) {
            throw new ReleaseBusyException;
        }

        $run = ReleaseRun::query()->create(['kind' => $kind, 'status' => 'running', 'actor_email' => $actor?->email, 'started_at' => now(), 'updated_at' => now()]);

        if (app()->runningUnitTests() || config('killdoku.release.inline', false)) {
            $this->execute($run);

            return $run->fresh();
        }

        $log = storage_path("logs/release-{$run->id}.log");
        $command = sprintf('nohup %s artisan puzzles:release %s --run=%d > %s 2>&1 &', escapeshellarg(PHP_BINARY), escapeshellarg($kind), $run->id, escapeshellarg($log));
        Process::fromShellCommandline($command, base_path())->run();

        return $run;
    }

    /** The body of a run, called in place or by the console command. */
    public function execute(ReleaseRun $run): void
    {
        set_time_limit(0);

        try {
            match ($run->kind) {
                'starter' => $this->service->fillStarter($run),
                'reserve' => $this->service->fillReserve($run),
                'daily' => $this->service->releaseToday($run),
                'story' => $this->service->buildStory($run),
            };
            $run->update(['status' => $run->failed > 0 && $run->created === 0 && $run->requested > 0 ? 'failed' : 'done', 'finished_at' => now(), 'updated_at' => now()]);
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'message' => mb_substr($exception->getMessage(), 0, 250), 'finished_at' => now(), 'updated_at' => now()]);
        }

        $this->service->audit($run->fresh());
    }

    /** The run that is going now, if any; a stalled one is closed first. */
    public function current(): ?ReleaseRun
    {
        $run = ReleaseRun::query()->where('status', 'running')->latest('id')->first();
        if ($run !== null && ($run->updated_at ?? $run->started_at)->lt(now()->subMinutes(self::STALE_MINUTES))) {
            $run->update(['status' => 'failed', 'message' => 'Остановлено: процесс давно не отвечает.', 'finished_at' => now()]);

            return null;
        }

        return $run;
    }

    /** @return Collection<int, ReleaseRun> */
    public function recent(int $limit = 8)
    {
        return ReleaseRun::query()->latest('id')->limit($limit)->get();
    }
}
