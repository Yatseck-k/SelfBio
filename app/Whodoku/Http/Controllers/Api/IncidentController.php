<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;
use Whodoku\Http\Requests\IncidentRequest;
use Whodoku\Models\Incident;
use Whodoku\Models\Puzzle;

class IncidentController extends Controller
{
    use ResolvesPlayer;

    /** Guests and players alike can report a case; the same words about the same case are kept once per day. */
    public function store(IncidentRequest $request, Puzzle $publishedPuzzle): JsonResponse
    {
        $player = $this->player($request);
        $reporter = hash('sha256', $player !== null ? "player:{$player->id}" : 'ip:'.$request->ip());
        $message = trim(preg_replace('/\s+/u', ' ', (string) $request->validated('message')) ?? '');

        $duplicate = Incident::query()
            ->where('puzzle_slug', $publishedPuzzle->slug)->where('reporter_hash', $reporter)
            ->where('message', $message)->where('created_at', '>=', now()->subDay())->exists();

        if (!$duplicate) {
            Incident::query()->create([
                'puzzle_id' => $publishedPuzzle->id,
                'puzzle_slug' => $publishedPuzzle->slug,
                'puzzle_revision' => $publishedPuzzle->revision,
                'player_id' => $player?->id,
                'category' => $request->validated('category'),
                'message' => $message,
                'context' => ['placements' => $request->validated('placements') ?? []],
                'reporter_hash' => $reporter,
                'status' => Incident::NEW,
            ]);
        }

        return response()->json(['message' => 'Спасибо! Мы проверим дело.'], 201);
    }
}
