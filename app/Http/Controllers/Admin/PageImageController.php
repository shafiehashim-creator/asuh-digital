<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Versi pantas": the admin's browser turns each PDF page into a light image
 * (PDF.js on a PC is quick) and uploads it here, so readers on phones get
 * small images instead of print-resolution PDF pages. Staff only.
 */
class PageImageController extends Controller
{
    public const MAX_PAGES = 2000;

    public function pdf(Request $request, BookFile $file): BinaryFileResponse
    {
        $this->authorizeStaff($request);
        abort_unless(Storage::disk(BookFile::DISK)->exists($file->path), 404);

        return response()->file(Storage::disk(BookFile::DISK)->path($file->path), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /** A new run replaces any earlier images for this file. */
    public function start(Request $request, BookFile $file): JsonResponse
    {
        $this->authorizeStaff($request);

        $file->forgetPageImages();
        $file->save();

        return response()->json(['ok' => true]);
    }

    public function store(Request $request, BookFile $file, int $page): JsonResponse
    {
        $this->authorizeStaff($request);
        abort_unless($page >= 1 && $page <= self::MAX_PAGES, 404);

        $request->validate([
            'image' => ['required', 'file', 'mimetypes:'.implode(',', BookFile::PAGE_FORMATS), 'max:5120'],
        ]);

        $format = array_search($request->file('image')->getMimeType(), BookFile::PAGE_FORMATS, true);
        $request->file('image')->storeAs($file->pageDirectory(), $page.'.'.$format, BookFile::DISK);

        return response()->json(['ok' => true]);
    }

    public function finish(Request $request, BookFile $file): JsonResponse
    {
        $this->authorizeStaff($request);

        $data = $request->validate([
            'pages' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PAGES],
            'format' => ['required', Rule::in(array_keys(BookFile::PAGE_FORMATS))],
            'width' => ['required', 'integer', 'min:1', 'max:10000'],
            'height' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $disk = Storage::disk(BookFile::DISK);
        foreach (range(1, $data['pages']) as $page) {
            abort_unless($disk->exists($file->pageImagePath($page, $data['format'])), 422, 'Halaman '.$page.' belum dimuat naik.');
        }

        $file->forceFill([
            'page_images' => $data['pages'],
            'page_format' => $data['format'],
            'page_width' => $data['width'],
            'page_height' => $data['height'],
            'pages_ready_at' => now(),
            'page_count' => $file->page_count ?? $data['pages'],
        ])->save();

        return response()->json(['ok' => true]);
    }

    private function authorizeStaff(Request $request): void
    {
        abort_unless($request->user()?->isStaff(), 403);
    }
}
