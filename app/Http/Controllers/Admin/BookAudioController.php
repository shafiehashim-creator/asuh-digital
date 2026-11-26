<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Staff listen to a page's voice from the book admin (signed link, staff only). */
class BookAudioController extends Controller
{
    public function page(Request $request, BookPage $page): BinaryFileResponse
    {
        abort_unless($request->user()?->isStaff(), 403);
        abort_unless($page->hasAudio() && Storage::disk(BookPage::DISK)->exists($page->audio_path), 404);

        return response()->file(Storage::disk(BookPage::DISK)->path($page->audio_path), [
            'Content-Type' => 'audio/mpeg',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
