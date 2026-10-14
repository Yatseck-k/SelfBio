<?php

declare(strict_types=1);

namespace Whodoku\Application\Release;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Whodoku\Application\Player\PlayerProgress;
use Whodoku\Application\Puzzle\GeneratedPuzzlePublicationException;
use Whodoku\Application\Puzzle\GeneratedPuzzlePublisher;
use Whodoku\Application\Puzzle\PublishOptions;
use Whodoku\Application\Story\StoryService;
use Whodoku\Domain\Puzzle\Generation\CaseCondition;
use Whodoku\Domain\Puzzle\Generation\GenerationProfile;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Puzzle;
use Whodoku\Models\ReleaseRun;

/**
 * The free release of cases: the starting set of a hundred by difficulty, the ten guest cases, the reserve of
 * checked cases waiting in the dark, and the daily batch that takes its cases out of the reserve.
 */
class ReleaseService
{
    /** How many times a missing case is tried again after the bot or the difficulty turned a variant down. */
    private const int TRIES_PER_CASE = 3;

    public function __construct(private readonly ?GeneratedPuzzlePublisher $publisher = null, private readonly ?PlayerProgress $progress = null) {}

    /** The date of today in the release timezone (Moscow), `Y-m-d`. */
    public function today(): string
    {
        return new CarbonImmutable('now', (string) config('killdoku.release.timezone'))->toDateString();
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $starter = [];
        foreach (config('killdoku.release.starter') as $level => $target) {
            $starter[$level] = ['target' => $target, 'have' => $this->base()->where('difficulty', $level)->count()];
        }
        $today = $this->today();

        return [
            'starter' => $starter,
            'starter_total' => ['target' => array_sum(config('killdoku.release.starter')), 'have' => $this->base()->count()],
            'guest' => ['target' => array_sum(config('killdoku.release.guest')), 'have' => $this->base()->where('guest_access', true)->count()],
            'reserve' => ['target' => (int) config('killdoku.release.reserve'), 'have' => Puzzle::query()->where('status', 'reserve')->count()],
            'story' => ['target' => count(config('story.chapters')), 'have' => Puzzle::query()->whereNotNull('story_chapter')->count()],
            'today' => $today,
            'released_today' => ['target' => (int) config('killdoku.release.daily'), 'have' => $this->releasedOn($today)],
            'recent_days' => Puzzle::query()->where('collection', 'daily')->where('status', 'published')->whereNotNull('released_on')
                ->selectRaw('released_on, count(*) as total')->groupBy('released_on')->latest('released_on')->limit(7)->pluck('total', 'released_on')->all(),
        ];
    }

    /** Fills the starting set up to the wanted number of cases per difficulty, then picks the guest cases. */
    public function fillStarter(ReleaseRun $run): void
    {
        $missing = [];
        foreach (config('killdoku.release.starter') as $level => $target) {
            $missing[$level] = max(0, $target - $this->base()->where('difficulty', $level)->count());
        }
        $run->update(['requested' => array_sum($missing), 'updated_at' => now()]);

        foreach ($missing as $level => $count) {
            for ($index = 0; $index < $count; $index++) {
                $this->generate($run, $level, new PublishOptions(PublishOptions::COLLECTION_BASE, false, 'published', null, $level, (int) config('killdoku.release.variants')));
            }
        }
        $this->assignGuestCases();
    }

    /** Generates and checks cases ahead of time, so a daily release is instant. */
    public function fillReserve(ReleaseRun $run): void
    {
        $missing = max(0, (int) config('killdoku.release.reserve') - Puzzle::query()->where('status', 'reserve')->count());
        $run->update(['requested' => $missing, 'updated_at' => now()]);

        foreach ($this->mixedLevels($missing) as $level) {
            $this->generate($run, $level, new PublishOptions(PublishOptions::COLLECTION_DAILY, false, 'reserve', null, $level, (int) config('killdoku.release.variants')));
        }
    }

    /** Makes today's cases public: from the reserve first, and generated on the spot when the reserve runs short. */
    public function releaseToday(ReleaseRun $run): void
    {
        $today = $this->today();
        $need = max(0, (int) config('killdoku.release.daily') - $this->releasedOn($today));
        $run->update(['requested' => $need, 'updated_at' => now()]);
        if ($need === 0) {
            $run->update(['message' => 'Сегодняшние дела уже выпущены.']);

            return;
        }

        foreach ($this->mixedLevels($need) as $level) {
            $reserved = Puzzle::query()->where('status', 'reserve')->where('difficulty', $level)->orderBy('id')->first()
                ?? Puzzle::query()->where('status', 'reserve')->orderBy('id')->first();
            if ($reserved !== null) {
                $reserved->update(['status' => 'published', 'published_at' => now(), 'released_on' => $today]);
                $run->update(['created' => $run->created + 1, 'updated_at' => now()]);

                continue;
            }
            $this->generate($run, $level, new PublishOptions(PublishOptions::COLLECTION_DAILY, false, 'published', $today, $level, (int) config('killdoku.release.variants')));
        }
    }

    /**
     * Exactly the configured number of the starting cases is open to guests, taken from the easiest ones.
     *
     * @return int how many cases guests open now
     */
    public function assignGuestCases(): int
    {
        $this->base()->update(['guest_access' => false]);

        $picked = [];
        foreach (config('killdoku.release.guest') as $level => $count) {
            array_push($picked, ...$this->base()->where('difficulty', $level)->orderBy('id')->limit($count)->pluck('id')->all());
        }
        $wanted = array_sum(config('killdoku.release.guest'));
        if (count($picked) < $wanted) {
            // A level with too few cases: the easiest remaining ones take its place.
            array_push($picked, ...$this->base()->whereNotIn('id', $picked)->orderBy('difficulty')->orderBy('id')->limit($wanted - count($picked))->pluck('id')->all());
        }
        Puzzle::query()->whereIn('id', $picked)->update(['guest_access' => true]);

        return count($picked);
    }

    /** Makes the cases of the story chapters that have none yet; the run shows the progress chapter by chapter. */
    public function buildStory(ReleaseRun $run): void
    {
        $story = new StoryService($this->progress ?? new PlayerProgress);
        $run->update(['requested' => count($story->missing()), 'updated_at' => now()]);

        $story->build($this->publisher, function (int $number, bool $made) use ($run): void {
            $run->update($made ? ['created' => $run->created + 1, 'updated_at' => now()] : ['failed' => $run->failed + 1, 'updated_at' => now()]);
        });
    }

    /** One case of the wanted difficulty: a few tries, then it is counted as failed. */
    private function generate(ReleaseRun $run, int $level, PublishOptions $options): void
    {
        $profiles = config('killdoku.release.profiles')[$level] ?? [['size' => [6, 6], 'conditions' => []]];
        $publisher = $this->publisher ?? new GeneratedPuzzlePublisher;

        for ($try = 0; $try < self::TRIES_PER_CASE; $try++) {
            $profile = $profiles[random_int(0, count($profiles) - 1)];
            [$rows, $cols] = $profile['size'];
            $conditions = array_map(CaseCondition::from(...), $profile['conditions']);
            try {
                $publisher->publish(GenerationProfile::forGrid($rows, $cols, seed: random_int(1, 2147483647), maxAttempts: 12, conditions: $conditions), null, $options);
                $run->update(['created' => $run->created + 1, 'updated_at' => now()]);

                return;
            } catch (GeneratedPuzzlePublicationException) {
                // Another size and seed next time.
            }
        }
        $run->update(['failed' => $run->failed + 1, 'updated_at' => now()]);
    }

    /**
     * The difficulties of the next cases of a daily batch (the mix of `release.daily_mix` per ten: now 1/2/3/2/2 for levels 1–5).
     *
     * @return list<int>
     */
    private function mixedLevels(int $count): array
    {
        $cycle = [];
        foreach (config('killdoku.release.daily_mix') as $level => $times) {
            array_push($cycle, ...array_fill(0, $times, $level));
        }

        return $cycle === [] ? [] : array_map(fn (int $index) => $cycle[$index % count($cycle)], range(0, max(0, $count - 1)));
    }

    private function releasedOn(string $date): int
    {
        return Puzzle::query()->where('collection', 'daily')->where('status', 'published')->whereDate('released_on', $date)->count();
    }

    /** @return Builder<Puzzle> */
    private function base()
    {
        return Puzzle::query()->where('collection', 'base')->where('status', 'published');
    }

    public function audit(ReleaseRun $run): void
    {
        AdminAuditLog::query()->create([
            'actor_email' => $run->actor_email, 'action' => 'release',
            'validation_result' => ['kind' => $run->kind, 'status' => $run->status, 'requested' => $run->requested, 'created' => $run->created, 'failed' => $run->failed],
        ]);
    }
}
