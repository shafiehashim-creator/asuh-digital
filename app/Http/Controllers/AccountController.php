<?php

namespace App\Http\Controllers;

use App\Enums\AffiliateStatus;
use App\Enums\Badge;
use App\Http\Middleware\EnsureCatalogOpen;
use App\Models\Book;
use App\Models\ChildBadge;
use App\Models\ReadingSession;
use App\Models\Setting;
use App\Models\User;
use App\Services\Affiliates;
use App\Services\FamilyProgress;
use App\Services\PlusOffer;
use App\Services\RewardPromises;
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
            'weeks' => $user->children()->with('badges', 'rewardPromises')->get()->map(fn ($child) => $this->progress->between($child, $from, $to)),
            'promises' => app(RewardPromises::class),
            'allBadges' => Badge::cases(),
        ]);
    }

    /** The how-to for parents: a picture of each step and the family's own checklist. */
    public function guide(Request $request): View
    {
        $user = $request->user();
        $children = $user->children()->get();

        return $this->page('account.guide', $user, [
            'children' => $children,
            'hasPin' => $user->hasParentPin(),
            'hasRead' => $children->isNotEmpty() && ReadingSession::query()->whereIn('child_id', $children->modelKeys())->exists(),
            'videosOpen' => $this->plus->available(),
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

    public function affiliate(Request $request, Affiliates $affiliates): View
    {
        $user = $request->user();
        $affiliate = $user->affiliate()->first();

        abort_unless($affiliate || $affiliates->isOpen(), 404);

        return $this->page('account.affiliate', $user, [
            'affiliate' => $affiliate,
            'summary' => $affiliate?->isActive() ? $affiliates->summary($affiliate) : null,
            'commissions' => $affiliate?->isActive() ? $affiliate->commissions()->with('order.plan', 'order.book')->latest()->limit(50)->get() : collect(),
            'percent' => $affiliate?->effectivePercent() ?? (float) Setting::get('affiliate_commission_percent'),
            'holdDays' => (int) Setting::get('affiliate_hold_days'),
            'cookieDays' => (int) Setting::get('affiliate_cookie_days'),
        ]);
    }

    public function applyAffiliate(Request $request, Affiliates $affiliates): RedirectResponse
    {
        $user = $request->user();
        $current = $user->affiliate()->first();
        abort_unless($current || $affiliates->isOpen(), 404);
        abort_if($current?->status === AffiliateStatus::Suspended, 403);

        $data = $request->validate([
            'promotion_channels' => ['required', 'string', 'max:1000'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_name' => ['required', 'string', 'max:150'],
            'bank_account_number' => ['required', 'string', 'max:40', 'regex:/^[0-9 -]+$/'],
        ], [], [
            'promotion_channels' => 'cara anda mempromosi',
            'bank_name' => 'nama bank',
            'bank_account_name' => 'nama pemegang akaun',
            'bank_account_number' => 'nombor akaun',
        ]);

        $wasActive = $current?->isActive();
        $affiliates->apply($user, $data);

        return redirect()->route('account.affiliate')->with('status', $wasActive ? 'affiliate-saved' : 'affiliate-applied');
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
