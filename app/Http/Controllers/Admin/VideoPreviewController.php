<?php

namespace App\Http\Controllers\Admin;

use App\Filament\Resources\Videos\VideoResource;
use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Support\VideoEmbed;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Staff watch any video, published or not, for editing and syariah review. Nothing is recorded. */
class VideoPreviewController extends Controller
{
    public function __invoke(Request $request, Video $video): View
    {
        abort_unless($request->user()?->isStaff(), 403);

        return view('admin.video-preview', [
            'video' => $video,
            'embed' => VideoEmbed::for($video),
            'backUrl' => VideoResource::getUrl('edit', ['record' => $video]),
        ]);
    }
}
