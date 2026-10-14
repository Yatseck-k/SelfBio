<?php

declare(strict_types=1);

namespace Whodoku\Application\Puzzle;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Whodoku\Domain\Puzzle\Validation\PuzzleStructureValidator;
use Whodoku\Models\AdminAuditLog;
use Whodoku\Models\Puzzle;
use Whodoku\Models\User;

class PuzzleMapUpdater
{
    public function update(Puzzle $puzzle, array $map, ?User $actor = null): Puzzle
    {
        $startedAt = hrtime(true);
        $data = Validator::make($map, [
            'blocked_cells' => ['present', 'array'],
            'blocked_cells.*' => ['array', 'size:2'],
            'blocked_cells.*.*' => ['integer'],
            'rooms' => ['present', 'array', 'min:1'],
            'rooms.*.code' => ['required', 'string', 'max:64'],
            'rooms.*.name' => ['required', 'string', 'max:255'],
            'rooms.*.cells' => ['required', 'array', 'min:1'],
            'rooms.*.cells.*' => ['array', 'size:2'],
            'rooms.*.cells.*.*' => ['integer'],
            'walls' => ['present', 'array'],
            'walls.*.from' => ['required', 'array', 'size:2'],
            'walls.*.to' => ['required', 'array', 'size:2'],
            'walls.*.from.*' => ['integer'], 'walls.*.to.*' => ['integer'],
            'windows' => ['sometimes', 'array'],
            'windows.*.cell' => ['required', 'array', 'size:2'],
            'windows.*.cell.*' => ['integer'],
            'windows.*.side' => ['required', 'in:north,south,west,east'],
            'objects' => ['present', 'array'],
            'objects.*.code' => ['required', 'string', 'max:64'],
            'objects.*.type' => ['required', 'string', 'max:64'],
            'objects.*.name' => ['required', 'string', 'max:255'],
            'objects.*.cell' => ['required', 'array', 'size:2'],
            'objects.*.cell.*' => ['integer'],
            'objects.*.blocking' => ['required', 'boolean'],
            'objects.*.occupiable' => ['required', 'boolean'],
        ])->validate();

        $walls = $this->walls($data['walls']);

        return DB::transaction(function () use ($actor, $data, $puzzle, $startedAt, $walls): Puzzle {
            $puzzle->rooms()->delete();
            $puzzle->walls()->delete();
            $puzzle->objects()->delete();
            $puzzle->update([
                'blocked_cells' => $data['blocked_cells'],
                // A map without a `windows` key keeps its windows.
                'windows' => $data['windows'] ?? $puzzle->windows ?? [],
                'status' => 'draft',
                'revision' => $puzzle->revision + 1,
                'published_at' => null,
            ]);
            foreach ($data['rooms'] as $room) {
                $puzzle->rooms()->create($room);
            }
            foreach ($walls as $wall) {
                $puzzle->walls()->create($wall);
            }
            foreach ($data['objects'] as $object) {
                $puzzle->objects()->create([
                    'code' => $object['code'], 'type' => $object['type'], 'name' => $object['name'],
                    'row' => $object['cell'][0], 'col' => $object['cell'][1],
                    'blocking' => $object['blocking'], 'occupiable' => $object['occupiable'],
                ]);
            }
            $definition = (new PuzzleDefinitionMapper)->fromModel($puzzle->fresh() ?? $puzzle);
            $issues = (new PuzzleStructureValidator)->validate($definition);
            if ($issues !== []) {
                throw new PuzzleMapUpdateException('Карта не прошла структурную проверку.');
            }
            AdminAuditLog::query()->create([
                'user_id' => $actor?->id, 'actor_email' => $actor?->email, 'puzzle_id' => $puzzle->id,
                'puzzle_slug' => $puzzle->slug, 'action' => 'map_update',
                'validation_result' => ['status' => 'draft', 'rooms' => count($data['rooms']), 'objects' => count($data['objects']), 'walls' => count($walls)],
                'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            ]);

            return $puzzle->fresh(['rooms', 'walls', 'objects']) ?? $puzzle;
        });
    }

    /** @return list<array{row_from:int,col_from:int,row_to:int,col_to:int}> */
    private function walls(array $walls): array
    {
        $result = [];
        foreach ($walls as $wall) {
            [$a, $b] = [$wall['from'], $wall['to']];
            if (abs($a[0] - $b[0]) + abs($a[1] - $b[1]) !== 1) {
                throw new PuzzleMapUpdateException('Стена должна соединять соседние клетки.');
            }
            if ($a > $b) {
                [$a, $b] = [$b, $a];
            }
            $key = implode(':', [...$a, ...$b]);
            if (isset($result[$key])) {
                throw new PuzzleMapUpdateException('Стены не должны повторяться.');
            }
            $result[$key] = ['row_from' => $a[0], 'col_from' => $a[1], 'row_to' => $b[0], 'col_to' => $b[1]];
        }

        return array_values($result);
    }
}
