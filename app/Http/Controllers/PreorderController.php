<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Exceptions\PreorderUnavailable;
use App\Http\Requests\StorePreorderRequest;
use App\Models\Order;
use App\Models\Setting;
use App\Services\PreorderCheckout;
use App\Services\PreorderSlots;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PreorderController extends Controller
{
    public function create(Request $request, PreorderSlots $slots, PreorderCheckout $checkout): View|RedirectResponse
    {
        if ($user = $request->user()) {
            if ($existing = $checkout->existingFor($user)) {
                return redirect()->route('preorder.pay', $existing->order);
            }
        } else {
            // A returning buyer who taps "Log masuk" here comes straight back.
            $request->session()->put('url.intended', route('preorder.create'));
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
                ->withErrors(['preorder' => 'Terlalu ramai pembeli serentak. Sila cuba sekali lagi.']);
        }

        if (! $user) {
            Auth::login($preorder->user);
            $request->session()->regenerate();
        }

        return redirect()->route('preorder.pay', $preorder->order);
    }

    public function pay(Request $request, Order $order): View
    {
        // Orders are only visible to their owner; 404 so references cannot be probed.
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load(['plan', 'preorder']);

        // Keep the "checking payment" notice across the auto-refreshes.
        if ($request->boolean('semak') && $order->status === OrderStatus::Pending) {
            $request->session()->flash('payment_checking', true);
        }

        return view('preorder.pay', [
            'order' => $order,
            'preorder' => $order->preorder,
            'isPending' => $order->status === OrderStatus::Pending,
            'isExpired' => $order->status === OrderStatus::Failed
                || ($order->preorder?->reserved_until?->isPast() && $order->status === OrderStatus::Pending),
        ]);
    }
}
