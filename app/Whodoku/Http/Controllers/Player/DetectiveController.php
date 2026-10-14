<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Player;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whodoku\Application\Player\DetectiveProfile;
use Whodoku\Application\Player\PlayerPresenter;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;

class DetectiveController extends Controller
{
    use ResolvesPlayer;

    public function store(Request $request, DetectiveProfile $detectives, PlayerPresenter $presenter): JsonResponse
    {
        $player = $this->player($request);
        abort_if($player === null, 401);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'gender' => ['nullable', 'in:m,f'],
            'skin' => ['nullable', 'integer'], 'hair' => ['nullable', 'integer'], 'style' => ['nullable', 'integer'],
            'shirt' => ['nullable', 'integer'], 'extra' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json(['player' => $presenter->present($detectives->save($player, $data))]);
    }
}
