<?php

namespace App\Http\Controllers;

use App\Enums\Badge;
use App\Http\Middleware\EnsureCatalogOpen;
use App\Models\Book;
use App\Models\ChildBadge;
use App\Models\User;
use App\Services\FamilyProgress;
use App\Services\PlusOffer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The parent's account area: a summary page and one page per part (shelf,
 * children, progress, subscription and orders, profile), sharing a side menu.
 */
class AccountController extends Controller
{
    public function __construct(private PlusOffer $plus, private FamilyProgress $progress) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Teachers land here after logging in; their home is the teacher portal.
        if ($user->isInstitutionStaff()) {
            return redirect()->route('teacher.home');
        }

        $shelfOpen = EnsureCatalogOpen::isOpenFor($user);
        $children = $shelfOpen ? $user->children()->get() : new Collection;
        [$from, $to] = FamilyProgress::thisWeek();
        $weeks = $children->map(fn ($child) => $this->progress->between($child, $from, $to));

        return $this->page('account.show', $user, [
            'children' => $children,
            'weeks' => $weeks->keyBy(fn ($week) => $week['child']->id),
            'weekMinutes' => $weeks->sum('minutes'),
            'badgeCount' => ChildBadge::query()->whereIn('child_id', $children->modelKeys())->count(),
            'continueReading' => $this->progress->continueReading($children),
            // Before any child has read, the parent's own recent books.
            'recentBooks' => $shelfOpen ? $this->recentBooks($user, 4) : new Collection,
            'ownedCount' => $user->entitlements()->count(),
            'orderCount' => $user->orders()->count(),
        ]);
    }

    public function shelf(Request $request): View
    {
        $user = $request->user();

        return $this->page('account.shelf', $user, [
            'recentBooks' => $this->recentBooks($user, 12),
            'ownedBooks' => $this->ownedBooks($user),
        ]);
    }

    public function children(Request $request): View
    {
        $user = $request->user();

        return $this->page('account.children', $user, [
            'children' => $user->children()->get(),
        ]);
    }

    public function progress(Request $request): View
    {
        $user = $request->user();
        [$from, $to] = FamilyProgress::thisWeek();

        return $this->page('account.progress', $user, [
            'from' => $from,
            'weeks' => $user->children()->with('badges')->get()->map(fn ($child) => $this->progress->between($child, $from, $to)),
            'allBadges' => Badge::cases(),
        ]);
    }

    public function billing(Request $request): View
    {
        $user = $request->user();

        return $this->page('account.billing', $user, [
            'orders' => $user->orders()->with(['plan', 'book'])->latest()->get(),
        ]);
    }

    public function profile(Request $request): View
    {
        return $this->page('account.profile', $request->user());
    }

    /** Every account page gets the subscription state for its side menu and upgrade card. */
    private function page(string $view, User $user, array $data = []): View
    {
        $activePass = $user->activePass();

        return view($view, $data + [
            'user' => $user,
            'activePass' => $activePass,
            // Paid before launch: the pass waits for the launch date (starts_at still null).
            'pendingPass' => $user->passes()->whereNull('starts_at')->with('plan')->latest()->first(),
            'shelfOpen' => EnsureCatalogOpen::isOpenFor($user),
            'canReadAll' => $activePass !== null || $user->isStaff(),
            // Phase 2: offer the Langganan Plus upgrade once it is on sale.
            'plusUpgradePrice' => $this->plus->available() && ($pass = $this->plus->upgradablePass($user)) ? $this->plus->upgradePriceSen($pass) : null,
        ]);
    }

    /**
     * Books opened lately that the family can still read (a lapsed pass drops them).
     *
     * @return Collection<int, Book>
     */
    private function recentBooks(User $user, int $limit): Collection
    {
        return $user->readBooks()->published()
            ->orderByPivot('last_read_at', 'desc')
            ->limit($limit * 2)
            ->get()
            ->filter(fn (Book $book) => $user->canReadBook($book))
            ->take($limit)
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
