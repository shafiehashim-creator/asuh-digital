<?php

namespace App\Http\Controllers;

use App\Enums\ChipMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\PreorderUnavailable;
use App\Http\Requests\StorePreorderRequest;
use App\Models\Order;
use App\Models\Setting;
use App\Services\BankTransfers;
use App\Services\Campaigns;
use App\Services\Chip\ChipClient;
use App\Services\Chip\ProcessingFee;
use App\Services\PreorderCheckout;
use App\Services\PreorderSlots;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PreorderController extends Controller
{
    public function create(Request $request, PreorderSlots $slots, PreorderCheckout $checkout, Campaigns $campaigns): View|RedirectResponse
    {
        if ($user = $request->user()) {
            if ($existing = $checkout->existingFor($user)) {
                return redirect()->route('preorder.pay', $existing->order);
            }
        } else {
            // A returning buyer who taps "Log masuk" here comes straight back.
            $request->session()->put('url.intended', route('preorder.create'));
        }

        // Outside the early bird campaign (coming soon or over), the normal price is on sale.
        if (! $campaigns->earlyBirdWindowOpen()) {
            return redirect()->route('purchase.pass');
        }

        return view('preorder.create', [
            'plan' => $slots->plan(),
            'soldOut' => $slots->isSoldOut(),
            'slotLimit' => $slots->limit(),
            'slotsRemaining' => $slots->remaining(),
            'reservationMinutes' => (int) Setting::get('preorder_reservation_minutes'),
            'bankTransferHours' => (int) Setting::get('bank_transfer_reservation_hours'),
        ]);
    }

    public function store(StorePreorderRequest $request, PreorderCheckout $checkout): RedirectResponse
    {
        $user = $request->user();

        if ($user && ($existing = $checkout->existingFor($user))) {
            return redirect()->route('preorder.pay', $existing->order);
        }

        try {
            // Guests send their account fields too; CreateNewUser validates them.
            $preorder = $checkout->reserve($user, $request->all());
        } catch (PreorderUnavailable $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['preorder' => $e->getMessage()]);
        } catch (LockTimeoutException) {
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['preorder' => __('Terlalu ramai pembeli serentak. Sila cuba sekali lagi.')]);
        }

        if (! $user) {
            Auth::login($preorder->user);
            $request->session()->regenerate();
        }

        return redirect()->route('preorder.pay', $preorder->order);
    }

    public function pay(Request $request, Order $order, BankTransfers $bankTransfers, ChipClient $chip, ProcessingFee $fees): View
    {
        // Orders are only visible to their owner; 404 so references cannot be probed.
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load(['plan', 'book', 'preorder']);

        // Keep the "checking payment" notice across the auto-refreshes.
        if ($request->boolean('semak') && $order->status === OrderStatus::Pending) {
            $request->session()->flash('payment_checking', true);
        }

        $awaitingConfirmation = $order->isAwaitingConfirmation();

        return view('preorder.pay', [
            'order' => $order,
            'preorder' => $order->preorder,
            // Where "try again" goes: the early bird form, the book, or the regular pass.
            'retryUrl' => match (true) {
                $order->preorder !== null => route('preorder.create'),
                $order->book !== null => route('catalog.show', $order->book),
                default => route('purchase.pass'),
            },
            'awaitingConfirmation' => $awaitingConfirmation,
            'canUploadReceipt' => $bankTransfers->canUploadReceipt($order),
            // FPX, e-wallet and card, each with the processing fee the buyer adds.
            'chipOptions' => $order->payment_method === PaymentMethod::Chip && $order->status === OrderStatus::Pending && $chip->isConfigured()
                ? collect($chip->offeredMethods())->map(fn (ChipMethod $method) => ['method' => $method, 'fee' => $fees->for($method, $order->amount_sen)])
                : collect(),
            'isExpired' => ! $awaitingConfirmation && (
                $order->status === OrderStatus::Failed
                || ($order->preorder?->reserved_until?->isPast() && $order->status === OrderStatus::Pending)
            ),
        ]);
    }
}
