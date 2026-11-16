<?php

namespace App\Http\Controllers;

use App\Models\BookSeries;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** One page per book series: its books in reading order. */
class SeriesController extends Controller
{
    public function show(Request $request, BookSeries $series): View
    {
        $user = $request->user();
        $books = ($user?->isStaff() ? $series->books() : $series->publishedBooks())->with('categories')->get();

        // A series with nothing published yet exists only for staff.
        abort_if($books->isEmpty(), 404);

        return view('series.show', [
            'series' => $series,
            'books' => $books->each(fn ($book) => $book->setAttribute('readable', (bool) $user?->canReadBook($book))),
            'hasPass' => $user && ($user->isStaff() || $user->passes()->active()->exists()),
        ]);
    }
}
