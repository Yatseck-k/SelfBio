<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Whodoku\Application\Player\PlayerSkills;
use Whodoku\Domain\Puzzle\Cell;
use Whodoku\Domain\Puzzle\Hints\HintComposer;
use Whodoku\Domain\Puzzle\Placement;
use Whodoku\Domain\Puzzle\PuzzleDefinition;
use Whodoku\Domain\Puzzle\Token;
use Whodoku\Domain\Puzzle\Validation\AuthorSolutionValidator;
use Whodoku\Models\ObjectKind;
use Whodoku\Models\Player;
use Whodoku\Models\Puzzle;
use Whodoku\Models\PuzzleHint;

class PublicPuzzleService
{
    public function __construct(
        private readonly PuzzleDefinitionMapper $mapper = new PuzzleDefinitionMapper,
        private readonly PlayerSkills $skills = new PlayerSkills,
    ) {}

    /**
     * The cases the viewer may open (a guest sees only the guest cases); `$all` is for the review bot.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(?Player $viewer = null, bool $all = false): array
    {
        $today = new CarbonImmutable('now', config('killdoku.release.timezone'))->toDateString();
        $entries = $all ? $this->catalogEntries(Puzzle::query()->where('status', 'published')) : $this->cachedCatalogEntries();

        $visible = $all || $viewer !== null ? $entries : array_filter($entries, fn (array $entry) => $entry['guest_access']);

        return array_values(array_map(function (array $entry) use ($viewer, $all, $today): array {
            // Closed by intellect: the player sees the card with «Откроется при …».
            $locked = !$all && $viewer !== null && !$entry['guest_access'] && !$this->skills->opensDifficulty($viewer, (int) $entry['difficulty']);
            $released = $entry['released_on'];

            return [
                ...$entry,
                'is_new' => $released === $today,
                'locked' => $locked,
                'unlock' => $locked ? $this->skills->unlockFor((int) $entry['difficulty']) : null,
            ];
        }, $visible));
    }

    /**
     * What the catalog shows of every case, the same for every viewer. The list of the whole house grows by ten cases
     * a day and carries the map of each, so it is kept in the cache and rebuilt when a case is added, published or
     * its map changes (every change of a map raises the revision of the case).
     *
     * @return list<array<string, mixed>>
     */
    private function cachedCatalogEntries(): array
    {
        $base = Puzzle::query()->where('status', 'published')->where('collection', '!=', PublishOptions::COLLECTION_STORY);
        $stamp = (clone $base)->toBase()->selectRaw('count(*) as total, max(updated_at) as latest, sum(revision) as revisions')->first();
        $key = 'catalog:v1:'.md5(json_encode($stamp));

        return Cache::remember($key, now()->addDay(), fn () => $this->catalogEntries($base));
    }

    /**
     * @param  Builder<Puzzle>  $query
     * @return list<array<string, mixed>>
     */
    private function catalogEntries(Builder $query): array
    {
        return $query
            ->with(['rooms' => fn ($query) => $query->orderBy('id'), 'walls', 'objects'])
            ->latest('released_on')
            ->latest('published_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Puzzle $puzzle) => [
                'slug' => $puzzle->slug,
                'collection' => $puzzle->collection,
                'guest_access' => (bool) $puzzle->guest_access,
                'released_on' => $puzzle->released_on?->toDateString(),
                'title' => $puzzle->title,
                'description' => $puzzle->description,
                'type' => $puzzle->type,
                'difficulty' => $puzzle->difficulty,
                'rows' => $puzzle->rows,
                'cols' => $puzzle->cols,
                'revision' => $puzzle->revision,
                'preview' => $this->preview($puzzle),
            ])->values()->all();
    }

    /**
     * Light map outline for catalog cards: rooms, walls and unavailable cells only, never placements.
     *
     * @return array<string, mixed>
     */
    private function preview(Puzzle $puzzle): array
    {
        $blocked = $puzzle->blocked_cells;
        foreach ($puzzle->objects as $object) {
            if ($object->blocking || !$object->occupiable) {
                $blocked[] = [$object->row, $object->col];
            }
        }

        return [
            'rooms' => $puzzle->rooms->map(fn ($room) => ['code' => $room->code, 'cells' => $room->cells])->all(),
            'walls' => $puzzle->walls->map(fn ($wall) => [
                'from' => [$wall->row_from, $wall->col_from], 'to' => [$wall->row_to, $wall->col_to],
            ])->all(),
            'blocked' => $blocked,
            'windows' => $puzzle->windows ?? [],
        ];
    }

    /** @return array<string, mixed> */
    public function detail(string $slug): array
    {
        $puzzle = $this->publishedPuzzle($slug);

        try {
            $this->mapper->fromModel($puzzle);
        } catch (InvalidPuzzleDataException) {
            throw (new ModelNotFoundException)->setModel(Puzzle::class, [$slug]);
        }

        $icons = ObjectKind::query()->pluck('icon', 'code')->all();

        return [
            'slug' => $puzzle->slug,
            'title' => $puzzle->title,
            'description' => $puzzle->description,
            'story' => $puzzle->story,
            'type' => $puzzle->type,
            'difficulty' => $puzzle->difficulty,
            'revision' => $puzzle->revision,
            'grid' => ['rows' => $puzzle->rows, 'cols' => $puzzle->cols, 'blocked_cells' => $puzzle->blocked_cells],
            'rooms' => $puzzle->rooms->map(fn ($room) => ['code' => $room->code, 'name' => $room->name, 'cells' => $room->cells])->all(),
            'walls' => $puzzle->walls->map(fn ($wall) => [
                'from' => [$wall->row_from, $wall->col_from], 'to' => [$wall->row_to, $wall->col_to],
            ])->all(),
            'windows' => $puzzle->windows ?? [],
            'victim' => $puzzle->victim,
            // The icon comes from the lists the administrator edits; older kinds fall back to the client's own.
            'objects' => $puzzle->objects->map(fn ($object) => [
                'code' => $object->code, 'type' => $object->type, 'name' => $object->name,
                'cell' => [$object->row, $object->col], 'blocking' => $object->blocking,
                'occupiable' => $object->occupiable, 'metadata' => $object->metadata,
                'icon' => $icons[$object->type] ?? null,
            ])->all(),
            'tokens' => $puzzle->tokens->map(fn ($token) => [
                'code' => $token->code, 'name' => $token->name, 'kind' => $token->kind,
                'description' => $token->description, 'avatar' => $token->avatar,
                'counts_for_row_col' => $token->counts_for_row_col, 'sort_order' => $token->sort_order,
                'metadata' => $token->metadata,
            ])->all(),
            'clues' => $puzzle->clues->map(fn ($clue) => [
                'text' => $clue->text, 'constraint_type' => $clue->constraint_type,
                'constraint' => $clue->constraint, 'sort_order' => $clue->sort_order,
            ])->all(),
        ];
    }

    /** @param array<string, array{int, int}> $payload
     * @return array<string, mixed>
     */
    public function check(string $slug, array $payload): array
    {
        $puzzle = $this->publishedPuzzle($slug);
        $definition = $this->definitionOrNotFound($puzzle, $slug);
        $placements = $this->placements($payload);
        $complete = $this->isComplete($definition->tokens, $placements);

        if (!$complete || (new AuthorSolutionValidator)->validate($definition, $placements) !== []) {
            return ['correct' => false, 'complete' => $complete];
        }

        $authorPlacements = $puzzle->solution?->placements;
        if (!is_array($authorPlacements)) {
            return ['correct' => false, 'complete' => true];
        }

        $author = $this->placements($authorPlacements);
        if ((new AuthorSolutionValidator)->validate($definition, $author) !== [] || $this->placementMap($placements) !== $this->placementMap($author)) {
            return ['correct' => false, 'complete' => true];
        }

        return ['correct' => true, 'complete' => true, 'ending' => $puzzle->ending];
    }

    /**
     * The hint depends on the player's current placements; the level (1-3) sets how much it reveals.
     * Puzzles without a usable author solution fall back to their stored hint texts.
     *
     * @param  array<string, array{int, int}>  $payload
     * @return array{level: int, text: string}
     */
    public function hint(string $slug, int $level, array $payload = []): array
    {
        $puzzle = $this->publishedPuzzle($slug);
        $definition = $this->definitionOrNotFound($puzzle, $slug);
        $level = max(1, min(HintComposer::MAX_LEVEL, $level));

        $authorPlacements = $puzzle->solution?->placements;
        if (is_array($authorPlacements) && $authorPlacements !== []) {
            $current = array_filter(
                $this->placements($payload),
                fn (Placement $placement) => $definition->tokenByCode($placement->tokenCode) !== null,
            );
            $composed = (new HintComposer)->compose($definition, $this->placements($authorPlacements), array_values($current), $level);

            return ['level' => $level, 'text' => $composed['text']];
        }

        $hint = $puzzle->hints->firstWhere('level', $level);
        if ($hint === null) {
            throw (new ModelNotFoundException)->setModel(PuzzleHint::class, [$level]);
        }

        return ['level' => $hint->level, 'text' => $hint->text];
    }

    private function publishedPuzzle(string $slug): Puzzle
    {
        return Puzzle::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->with([
                'rooms' => fn ($query) => $query->orderBy('id'),
                'walls' => fn ($query) => $query->orderBy('id'),
                'objects' => fn ($query) => $query->orderBy('id'),
                'tokens' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'clues' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'hints' => fn ($query) => $query->orderBy('level'),
                'solution',
            ])
            ->firstOrFail();
    }

    private function definitionOrNotFound(Puzzle $puzzle, string $slug): PuzzleDefinition
    {
        try {
            return $this->mapper->fromModel($puzzle);
        } catch (InvalidPuzzleDataException) {
            throw (new ModelNotFoundException)->setModel(Puzzle::class, [$slug]);
        }
    }

    /** @param array<string, array{int, int}> $payload
     * @return list<Placement>
     */
    private function placements(array $payload): array
    {
        return array_map(
            fn (string $token, array $cell) => new Placement($token, new Cell($cell[0], $cell[1])),
            array_keys($payload),
            array_values($payload),
        );
    }

    /** @param list<Token> $tokens
     * @param  list<Placement>  $placements
     */
    private function isComplete(array $tokens, array $placements): bool
    {
        $expected = array_map(fn ($token) => $token->code, $tokens);
        $actual = array_map(fn (Placement $placement) => $placement->tokenCode, $placements);
        sort($expected);
        sort($actual);

        return $expected === $actual;
    }

    /**
     * @param  list<Placement>  $placements
     * @return array<string, array{int, int}>
     */
    private function placementMap(array $placements): array
    {
        $map = [];
        foreach ($placements as $placement) {
            $map[$placement->tokenCode] = [$placement->cell->row, $placement->cell->col];
        }
        ksort($map);

        return $map;
    }
}
