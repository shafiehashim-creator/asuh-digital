<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCatalogOpen;
use App\Models\Book;
use App\Models\User;
use App\Services\PlusOffer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request, PlusOffer $plus): View|RedirectResponse
    {
        $user = $request->user();

        // Teachers land here after logging in; their home is the teacher portal.
        if ($user->isInstitutionStaff()) {
            return redirect()->route('teacher.home');
        }
        $activePass = $user->activePass();
        $shelfOpen = EnsureCatalogOpen::isOpenFor($user);

        return view('account.show', [
            'user' => $user,
            'activePass' => $activePass,
            // Includes passes waiting for the launch date (starts_at still null).
            'passes' => $user->passes()->with('plan')->latest()->get(),
            'orders' => $user->orders()->with(['plan', 'book'])->latest()->get(),
            // The shelf links into the reader, so it stays hidden while the catalogue is closed.
            'shelfOpen' => $shelfOpen,
            'recentBooks' => $shelfOpen ? $this->recentBooks($user) : new Collection,
            'ownedBooks' => $shelfOpen ? $this->ownedBooks($user) : new Collection,
            'canReadAll' => $activePass !== null || $user->isStaff(),
            'children' => $shelfOpen ? $user->children()->get() : new Collection,
            // Phase 2: offer the Langganan Plus upgrade once it is on sale.
            'plusUpgradePrice' => $plus->available() && ($pass = $plus->upgradablePass($user)) ? $plus->upgradePriceSen($pass) : null,
        ]);
    }

    /**
     * Books opened lately that the family can still read (a lapsed pass drops them).
     *
     * @return Collection<int, Book>
     */
    private function recentBooks(User $user): Collection
    {
        return $user->readBooks()->published()
            ->orderByPivot('last_read_at', 'desc')
            ->limit(12)
            ->get()
            ->filter(fn (Book $book) => $user->canReadBook($book))
            ->take(6)
            ->values();
    }

    /**
     * Single books bought for keeps.
     *
     * @return Collection<int, Book>
     */
    private function ownedBooks(User $user): Collection
    {
        return Book::query()->published()
            ->whereIn('id', $user->entitlements()->select('book_id'))
            ->orderBy('title')
            ->get();
    }
}
