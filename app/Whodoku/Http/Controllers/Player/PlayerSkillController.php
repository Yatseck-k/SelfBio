<?php

declare(strict_types=1);

namespace Whodoku\Http\Controllers\Player;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Whodoku\Application\Player\PlayerPresenter;
use Whodoku\Application\Player\PlayerSkills;
use Whodoku\Http\Controllers\Concerns\ResolvesPlayer;
use Whodoku\Http\Controllers\Controller;

/** Development of the detective: put a development point into a skill, take the points back. */
class PlayerSkillController extends Controller
{
    use ResolvesPlayer;

    public function store(Request $request, PlayerSkills $skills, PlayerPresenter $presenter): JsonResponse
    {
        $player = $this->player($request);
        abort_if($player === null, 401);
        $data = $request->validate(['skill' => ['required', 'string', 'in:'.implode(',', PlayerSkills::SKILLS)]]);

        if (!$skills->allocate($player, $data['skill'])) {
            return response()->json(['code' => 'no_points', 'message' => 'Свободных очков развития нет: они появляются с новым уровнем.'], 422);
        }

        return response()->json(['player' => $presenter->present($player->fresh() ?? $player)]);
    }

    public function reset(Request $request, PlayerSkills $skills, PlayerPresenter $presenter): JsonResponse
    {
        $player = $this->player($request);
        abort_if($player === null, 401);

        if (!$skills->reset($player)) {
            $state = $skills->resetState($player);

            return response()->json([
                'code' => 'reset_unavailable',
                'message' => $state['next_level'] === null ? 'Сбрасывать пока нечего.' : "Очки можно перераспределить раз в 20 уровней. Следующий раз — на уровне {$state['next_level']}.",
                'next_level' => $state['next_level'],
            ], 422);
        }

        return response()->json(['player' => $presenter->present($player->fresh() ?? $player)]);
    }
}
