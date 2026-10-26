<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\Book;
use App\Models\Order;
use App\Services\PlusOffer;
use App\Services\Purchases;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    public function __construct(private Purchases $purchases) {}

    public function pass(Request $request): View|RedirectResponse
    {
        // Early bird first; the regular price applies once its slots are gone.
        if ($this->purchases->earlyBirdOpen()) {
            return redirect()->route('preorder.create');
        }

        $plan = $this->purchases->regularPlan();
        abort_unless($plan, 404);

        $this->rememberForLogin($request, route('purchase.pass'));

        return view('purchase.create', [
            'title' => __('Beli Langganan Tahunan'),
            'item' => __($plan->name),
            'price' => Money::short($plan->price_sen),
            'details' => [__('Semua e-book ASUH Digital selama 12 bulan'), __('Sehingga 4 profil anak'), __('Sekali bayar, tiada caj automatik')],
            'note' => $request->user()?->activePass()
                ? __('Anda masih ada langganan aktif. Langganan baharu bermula sebaik langganan semasa tamat, jadi tiada hari yang hilang.')
                : null,
            'action' => route('purchase.pass.store'),
            'promoCode' => true,
        ]);
    }

    public function storePass(PurchaseRequest $request): RedirectResponse
    {
        if ($this->purchases->earlyBirdOpen()) {
            return redirect()->route('preorder.create');
        }

        return $this->complete($request, fn () => $this->purchases->buyPass($request->user(), $request->all()));
    }

    /**
     * Langganan Plus (Phase 2): a new subscription, or for families on
     * Langganan Tahunan an upgrade at the difference for the days left.
     */
    public function plus(Request $request, PlusOffer $offer): View|RedirectResponse
    {
        abort_unless($offer->available() || ($request->user()?->isStaff() && $offer->plan()), 404);

        $plan = $offer->plan();
        $user = $request->user();
        $upgrade = $user ? $offer->upgradablePass($user) : null;
        $upgradePrice = $upgrade ? $offer->upgradePriceSen($upgrade) : null;

        $this->rememberForLogin($request, route('purchase.plus'));

        if ($upgrade && $upgradePrice) {
            return view('purchase.create', [
                'title' => __('Naik taraf ke Langganan Plus'),
                'item' => __('Naik taraf ke :plan', ['plan' => __($plan->name)]),
                'price' => Money::short($upgradePrice),
                'details' => [
                    __('Semua video animasi ASUH, ditambah e-book yang sedia ada'),
                    $upgrade->ends_at
                        ? __('Untuk baki langganan anda, hingga :date', ['date' => $upgrade->ends_at->translatedFormat('j F Y')])
                        : __('Untuk seluruh tempoh langganan anda'),
                    __('Anda hanya membayar beza harga untuk hari yang berbaki'),
                ],
                'note' => null,
                'action' => route('purchase.plus.store'),
            ]);
        }

        return view('purchase.create', [
            'title' => __('Beli Langganan Plus'),
            'item' => __($plan->name),
            'price' => Money::short($plan->price_sen),
            'details' => [__('Semua e-book dan video animasi ASUH Digital selama 12 bulan'), __('Sehingga 4 profil anak'), __('Sekali bayar, tiada caj automatik')],
            'note' => $user?->activePass()?->isPlus()
                ? __('Anda masih ada Langganan Plus aktif. Langganan baharu bermula sebaik langganan semasa tamat, jadi tiada hari yang hilang.')
                : null,
            'action' => route('purchase.plus.store'),
            'promoCode' => true,
        ]);
    }

    public function storePlus(PurchaseRequest $request, PlusOffer $offer): RedirectResponse
    {
        $user = $request->user();

        return $this->complete($request, fn () => $user && $offer->upgradablePass($user)
            ? $this->purchases->upgradeToPlus($user, $request->all())
            : $this->purchases->buyPlus($user, $request->all()));
    }

    public function book(Request $request, Book $book): View|RedirectResponse
    {
        abort_unless($book->isPublished() && $book->single_price_sen, 404);

        if ($request->user()?->canReadBook($book)) {
            return redirect()->route('catalog.show', $book);
        }

        $this->rememberForLogin($request, route('purchase.book', $book));

        return view('purchase.create', [
            'title' => __('Beli e-book'),
            'item' => $book->localTitle(),
            'price' => Money::short($book->single_price_sen),
            'details' => [__('Baca dalam pelayar, di telefon atau tablet'), __('Kekal dalam akaun anda selama-lamanya')],
            'note' => null,
            'action' => route('purchase.book.store', $book),
            'promoCode' => true,
        ]);
    }

    public function storeBook(PurchaseRequest $request, Book $book): RedirectResponse
    {
        return $this->complete($request, fn () => $this->purchases->buyBook($request->user(), $book, $request->all()));
    }

    /**
     * @param  callable(): Order  $buy
     */
    private function complete(PurchaseRequest $request, callable $buy): RedirectResponse
    {
        try {
            $order = $buy();
        } catch (InvalidArgumentException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['purchase' => $e->getMessage()]);
        }

        if (! $request->user()) {
            Auth::login($order->user);
            $request->session()->regenerate();
        }

        return redirect()->route('preorder.pay', $order);
    }

    /**
     * A buyer who taps "Log masuk" on this page comes straight back after.
     */
    private function rememberForLogin(Request $request, string $url): void
    {
        if (! $request->user()) {
            $request->session()->put('url.intended', $url);
        }
    }
}
