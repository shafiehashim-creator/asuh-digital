<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseRequest;
use App\Models\Book;
use App\Models\Order;
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
            'title' => 'Beli Pas Tahunan',
            'item' => $plan->name,
            'price' => Money::short($plan->price_sen),
            'details' => ['Semua e-book ASUH Digital selama 12 bulan', 'Sehingga 4 profil anak', 'Sekali bayar, tiada caj automatik'],
            'note' => $request->user()?->activePass()
                ? 'Anda masih ada Pas aktif. Pas baharu bermula sebaik Pas semasa tamat, jadi tiada hari yang hilang.'
                : null,
            'action' => route('purchase.pass.store'),
        ]);
    }

    public function storePass(PurchaseRequest $request): RedirectResponse
    {
        if ($this->purchases->earlyBirdOpen()) {
            return redirect()->route('preorder.create');
        }

        return $this->complete($request, fn () => $this->purchases->buyPass($request->user(), $request->all()));
    }

    public function book(Request $request, Book $book): View|RedirectResponse
    {
        abort_unless($book->isPublished() && $book->single_price_sen, 404);

        if ($request->user()?->canReadBook($book)) {
            return redirect()->route('catalog.show', $book);
        }

        $this->rememberForLogin($request, route('purchase.book', $book));

        return view('purchase.create', [
            'title' => 'Beli e-book',
            'item' => $book->title,
            'price' => Money::short($book->single_price_sen),
            'details' => ['Baca dalam pelayar, di telefon atau tablet', 'Kekal dalam akaun anda selama-lamanya'],
            'note' => null,
            'action' => route('purchase.book.store', $book),
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
