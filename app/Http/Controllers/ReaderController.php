<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookFile;
use App\Services\ChildMode;
use App\Services\ReadingLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * CLAUDE.md "Perlindungan e-book": files live in storage/app/private and are
 * served only here, after a login and access check, through short-lived
 * signed URLs that are rate limited and bound to the signed-in user. The
 * reader draws a light watermark with the account e-mail on every page.
 */
class ReaderController extends Controller
{
    /**
     * How long a file link works. The reader fetches the PDF in pieces while the
     * child reads (HTTP range requests), so the link must outlive a reading
     * session; every piece is still checked against the signed-in user.
     */
    public const LINK_MINUTES = 180;

    public function show(Request $request, Book $book, ChildMode $childMode, ReadingLog $log): View|RedirectResponse
    {
        $user = $request->user();
        $child = $childMode->child($user);

        // Mod Anak: only books on the child's own shelf, and back to it on close.
        if ($child && ! $childMode->allows($child, $book)) {
            return redirect()->route('child-mode.home');
        }

        if (! $user->canReadBook($book)) {
            return redirect()->route('catalog.show', $book)
                ->with('reader_denied', true);
        }

        $file = $book->load('files')->readableFile();
        abort_unless($file, 404);

        $book->recordReader($user);

        $expires = now()->addMinutes(self::LINK_MINUTES);
        $link = ['book' => $book->slug, 'file' => $file->id, 'u' => $user->id];

        return view('reader.show', [
            'book' => $book,
            'fileUrl' => URL::temporarySignedRoute('reader.file', $expires, $link),
            // "Versi pantas": light page images, one signed link per page; the PDF stays the fallback.
            'pageImages' => $file->hasPageImages() ? [
                'width' => $file->page_width,
                'height' => $file->page_height,
                'urls' => array_map(
                    fn (int $page) => URL::temporarySignedRoute('reader.page', $expires, $link + ['page' => $page]),
                    range(1, $file->page_images),
                ),
            ] : null,
            'watermark' => $user->email.' · '.now()->translatedFormat('j M Y'),
            'closeUrl' => $child ? route('child-mode.home') : route('catalog.show', $book),
            // Mod Anak: reading time and the page reached are kept for the child.
            'progress' => $child ? [
                'url' => route('child-mode.progress', $book),
                'startPage' => $log->lastPage($child, $book),
            ] : null,
        ]);
    }

    public function file(Request $request, Book $book, BookFile $file): BinaryFileResponse
    {
        $this->authorizeLink($request, $book, $file);
        abort_unless(Storage::disk(BookFile::DISK)->exists($file->path), 404);

        // A file response (not a stream) answers Range requests, so PDF.js can
        // show the first pages without downloading the whole book.
        $response = response()->file(Storage::disk(BookFile::DISK)->path($file->path), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);

        return $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $book->slug.'.pdf');
    }

    public function page(Request $request, Book $book, BookFile $file, int $page): BinaryFileResponse
    {
        $this->authorizeLink($request, $book, $file);
        abort_unless($file->hasPageImages() && $page >= 1 && $page <= $file->page_images, 404);

        $path = $file->pageImagePath($page);
        abort_unless(Storage::disk(BookFile::DISK)->exists($path), 404);

        return response()->file(Storage::disk(BookFile::DISK)->path($path), [
            'Content-Type' => BookFile::PAGE_FORMATS[$file->page_format],
            // The signed link is unique to this reader and session, so a private cache is safe.
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * The signature proves the link is ours and fresh; these prove it is being
     * used by the person it was made for, who may still read it.
     */
    private function authorizeLink(Request $request, Book $book, BookFile $file): void
    {
        abort_unless((int) $request->query('u') === $request->user()->id, 403);
        abort_unless($file->book_id === $book->id, 404);
        abort_unless($request->user()->canReadBook($book), 403);
    }
}
