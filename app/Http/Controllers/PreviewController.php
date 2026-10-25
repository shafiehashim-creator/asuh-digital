<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookFile;
use App\Services\PassOffer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "Baca pratonton percuma": the first few pages of a book for anyone, no
 * login, in the normal reader. Only the light page images are served, and
 * only up to the preview limit; the rest of the book stays behind the pass.
 */
class PreviewController extends Controller
{
    public const LINK_MINUTES = 60;

    public function show(Book $book, PassOffer $offer): View
    {
        $book->load('files');
        $pages = $book->previewPageCount();
        abort_unless($pages > 0, 404);

        $file = $book->readableFile();
        $expires = now()->addMinutes(self::LINK_MINUTES);

        return view('reader.show', [
            'book' => $book,
            'fileUrl' => '',
            'pageImages' => [
                'width' => $file->page_width,
                'height' => $file->page_height,
                'urls' => array_map(
                    fn (int $page) => URL::temporarySignedRoute('catalog.preview.page', $expires, ['book' => $book->slug, 'page' => $page]),
                    range(1, $pages),
                ),
            ],
            'watermark' => 'Pratonton · ASUH Digital',
            'preview' => [
                'pages' => $pages,
                'total' => $file->page_count ?? $file->page_images,
                'offer' => $offer,
            ],
        ]);
    }

    public function page(Book $book, int $page): BinaryFileResponse
    {
        $book->load('files');
        abort_unless($page >= 1 && $page <= $book->previewPageCount(), 404);

        $file = $book->readableFile();
        $path = $file->pageImagePath($page);
        abort_unless(Storage::disk(BookFile::DISK)->exists($path), 404);

        return response()->file(Storage::disk(BookFile::DISK)->path($path), [
            'Content-Type' => BookFile::PAGE_FORMATS[$file->page_format],
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}
