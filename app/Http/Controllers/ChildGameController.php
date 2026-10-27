<?php

namespace App\Http\Controllers;

use App\Enums\Game;
use App\Services\ChildMode;
use App\Services\GamePlaying;
use App\Support\GameContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Playing a game in Mod Anak, and the heartbeat that counts the time played. */
class ChildGameController extends Controller
{
    public function __construct(private ChildMode $childMode, private GamePlaying $playing) {}

    public function show(Request $request, Game $game): View|RedirectResponse
    {
        $child = $this->childMode->child($request->user());
        abort_unless($child && $this->playing->hasAccess($child) && GameContent::playable($game, $child->age_group), 404);

        if ($this->playing->secondsLeftToday($child) === 0) {
            return redirect()->route('child-mode.home')->with('game_locked', 'Masa bermain hari ini sudah habis. Jom baca buku pula!');
        }

        // The level asked for, never above the highest one opened.
        $unlocked = $this->playing->unlockedLevel($child, $game);
        $level = max(1, min($unlocked, (int) $request->query('tahap', $unlocked)));

        return view('child-mode.game', [
            'child' => $child,
            'game' => $game,
            'level' => $level,
            'unlocked' => $unlocked,
            'content' => GameContent::for($game, $child->age_group, $level),
            'progressUrl' => route('child-mode.game.progress', $game),
            'secondsLeft' => $this->playing->secondsLeftToday($child),
            'best' => $this->playing->bestScores($child)[$game->value] ?? null,
        ]);
    }

    public function progress(Request $request, Game $game): JsonResponse
    {
        $child = $this->childMode->child($request->user());
        abort_unless($child && $this->playing->hasAccess($child), 403);

        $data = $request->validate([
            'seconds' => ['required', 'integer', 'min:0', 'max:600'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'level' => ['nullable', 'integer', 'min:1', 'max:'.count(Game::LEVELS)],
        ]);

        $score = isset($data['score']) ? (int) $data['score'] : null;
        $this->playing->record($child, $game, (int) $data['seconds'], $score);

        $unlocked = $score !== null
            ? $this->playing->finishRound($child, $game, (int) ($data['level'] ?? 1), $score)
            : $this->playing->unlockedLevel($child, $game);

        return response()->json(['seconds_left' => $this->playing->secondsLeftToday($child), 'unlocked' => $unlocked]);
    }
}
