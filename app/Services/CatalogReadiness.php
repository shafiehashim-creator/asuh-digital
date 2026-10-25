<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookFile;
use Illuminate\Support\Collection;

/**
 * What to fix before the admin opens the catalogue to the public
 * ("catalog_open"). Shown on the Settings page; nothing here blocks the switch.
 */
class CatalogReadiness
{
    /** Titles listed per problem before "dan N lagi". */
    private const NAMES_SHOWN = 3;

    /**
     * Problems found, in Malay for the admin, empty when ready.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $books = Book::query()->published()->with('files')->orderBy('title')->get();

        if ($books->isEmpty()) {
            return ['Belum ada buku yang diterbitkan. Katalog akan kosong.'];
        }

        $problems = [];

        $noFile = $books->filter(fn (Book $book) => $book->readableFile() === null);
        if ($noFile->isNotEmpty()) {
            $problems[] = 'Tiada fail PDF, jadi tidak boleh dibaca: '.$this->names($noFile);
        }

        $noPages = $books->filter(fn (Book $book) => ($file = $book->readableFile()) instanceof BookFile && ! $file->hasPageImages());
        if ($noPages->isNotEmpty()) {
            $problems[] = 'Belum ada versi pantas (lambat di telefon, tiada pratonton percuma): '.$this->names($noPages);
        }

        $noCover = $books->filter(fn (Book $book) => blank($book->cover_path));
        if ($noCover->isNotEmpty()) {
            $problems[] = 'Belum ada kaver: '.$this->names($noCover);
        }

        return $problems;
    }

    /** @param Collection<int, Book> $books */
    private function names($books): string
    {
        $shown = $books->take(self::NAMES_SHOWN)->pluck('title')->join(', ');
        $more = $books->count() - self::NAMES_SHOWN;

        return $more > 0 ? "{$shown} dan {$more} lagi." : "{$shown}.";
    }
}
