<?php

namespace App\Http\Controllers;

use App\Services\PlusOffer;
use App\Services\VideoShowcase;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The public animated video page (Phase 2): free samples, locked videos and the trailer. */
class VideoPageController extends Controller
{
    public function __invoke(Request $request, VideoShowcase $showcase, PlusOffer $offer): View
    {
        $user = $request->user();
        abort_unless($showcase->isOpenFor($user), 404);

        $videos = $showcase->videos();

        return view('videos.index', [
            'videos' => $videos,
            'groups' => $showcase->byCategory($videos),
            'lockedCount' => $videos->where('is_free_sample', false)->count(),
            'trailerId' => $showcase->trailerId(),
            'cta' => $showcase->cta($user),
            'showcase' => $showcase,
            'price' => $offer->videoPlanPriceSen(),
            'planName' => $offer->videoPlanName(),
        ]);
    }
}
