<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookFile;
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

    public function show(Request $request, Book $book): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->canReadBook($book)) {
            return redirect()->route('catalog.show', $book)
                ->with('reader_denied', true);
        }

        $file = $book->load('files')->readableFile();
        abort_unless($file, 404);

        return view('reader.show', [
            'book' => $book,
            'fileUrl' => URL::temporarySignedRoute('reader.file', now()->addMinutes(self::LINK_MINUTES), [
                'book' => $book->slug,
                'file' => $file->id,
                'u' => $user->id,
            ]),
            'watermark' => $user->email.' · '.now()->translatedFormat('j M Y'),
        ]);
    }

    public function file(Request $request, Book $book, BookFile $file): BinaryFileResponse
    {
        $user = $request->user();

        // The signature proves the link is ours and fresh; these prove it is
        // being used by the person it was made for, who may still read it.
        abort_unless((int) $request->query('u') === $user->id, 403);
        abort_unless($file->book_id === $book->id, 404);
        abort_unless($user->canReadBook($book), 403);
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
}
