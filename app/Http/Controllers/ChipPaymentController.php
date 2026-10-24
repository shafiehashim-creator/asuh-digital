<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\PaymentLog;
use App\Services\Chip\ChipClient;
use App\Services\PaymentProcessor;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChipPaymentController extends Controller
{
    public function __construct(private ChipClient $chip) {}

    /**
     * Send the buyer to CHIP's hosted checkout, reusing an unpaid purchase
     * so a double click can never create two payable purchases.
     */
    public function start(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->payment_method === PaymentMethod::Chip, 404);

        $backToOrder = redirect()->route('preorder.pay', $order);

        if ($order->status !== OrderStatus::Pending || $order->preorder?->reserved_until?->isPast()) {
            return $backToOrder;
        }

        if (! $this->chip->isConfigured()) {
            return $backToOrder->withErrors(['payment' => 'Bayaran dalam talian belum dibuka. Sila cuba sebentar lagi.']);
        }

        try {
            if ($order->chip_purchase_id) {
                $existing = $this->chip->getPurchase($order->chip_purchase_id);

                if (in_array($existing['status'] ?? null, ChipClient::PAYABLE_STATUSES, true) && ! empty($existing['checkout_url'])) {
                    return redirect()->away($existing['checkout_url']);
                }

                if (($existing['status'] ?? null) === 'paid') {
                    // Paid but the callback has not arrived yet; wait for it.
                    return $backToOrder->with('payment_checking', true);
                }
            }

            $purchase = $this->chip->createPurchase($order->loadMissing('user', 'plan'), $this->urls($order));
        } catch (RequestException $e) {
            Log::warning('CHIP purchase request failed', ['order' => $order->reference, 'status' => $e->response?->status()]);

            return $backToOrder->withErrors(['payment' => 'Maaf, kami tidak dapat menghubungi sistem bayaran. Sila cuba lagi.']);
        }

        $order->update(['chip_purchase_id' => $purchase['id']]);

        return redirect()->away($purchase['checkout_url']);
    }

    /**
     * Where CHIP sends the buyer back. This never marks anything as paid:
     * only the signed callback does. It just shows a "checking" notice.
     */
    public function back(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $redirect = redirect()->route('preorder.pay', $order);

        if ($request->query('result') === 'failed') {
            return $redirect->withErrors(['payment' => 'Bayaran tidak berjaya. Anda boleh cuba sekali lagi.']);
        }

        return $redirect->with('payment_checking', ! $order->isPaid());
    }

    /**
     * success_callback / portal webhook from CHIP. Every call is logged.
     */
    public function callback(Request $request, PaymentProcessor $payments): Response
    {
        $raw = $request->getContent();
        $payload = json_decode($raw, true) ?: [];

        try {
            $valid = $this->chip->verifySignature($raw, $request->header('X-Signature'));
        } catch (Throwable $e) {
            // e.g. the public key could not be fetched; let CHIP retry later.
            Log::error('CHIP signature check failed', ['error' => $e->getMessage()]);
            $this->log($request, $payload, null);

            return response('Signature check unavailable', 503);
        }

        $order = $this->findOrder($payload);
        $this->log($request, $payload, $valid, $order);

        if (! $valid) {
            return response('Invalid signature', 400);
        }

        if (! $order) {
            return response('Unknown order', 200); // nothing to retry
        }

        if (($payload['status'] ?? null) !== 'paid') {
            return response('Ignored', 200);
        }

        // Do not trust the callback body alone: ask CHIP for the purchase.
        try {
            $purchase = $this->chip->getPurchase($payload['id']);
        } catch (RequestException) {
            return response('Could not confirm purchase', 503);
        }

        if (! $this->confirmsPayment($purchase, $order)) {
            Log::warning('CHIP purchase did not confirm payment', ['order' => $order->reference, 'purchase' => $payload['id']]);

            return response('Not confirmed', 200);
        }

        $payments->markPaid($order);

        return response('OK', 200);
    }

    private function confirmsPayment(array $purchase, Order $order): bool
    {
        return ($purchase['status'] ?? null) === 'paid'
            && ($purchase['id'] ?? null) === $order->chip_purchase_id
            && (int) ($purchase['purchase']['total'] ?? -1) === $order->amount_sen
            && ($purchase['purchase']['currency'] ?? null) === $order->currency
            // A test purchase must never activate a pass on the live site.
            && (bool) ($purchase['is_test'] ?? true) === (bool) config('services.chip.test_mode');
    }

    private function findOrder(array $payload): ?Order
    {
        if (empty($payload['id'])) {
            return null;
        }

        // Matched by our stored purchase id, never by the reference alone.
        return Order::query()->where('chip_purchase_id', $payload['id'])->first();
    }

    private function log(Request $request, array $payload, ?bool $valid, ?Order $order = null): void
    {
        PaymentLog::query()->create([
            'provider' => 'chip',
            'event' => $payload['event_type'] ?? ($payload['status'] ?? null),
            'reference' => $payload['reference'] ?? null,
            'order_id' => $order?->id,
            'signature_valid' => $valid,
            'payload' => $payload,
            'ip_address' => $request->ip(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function urls(Order $order): array
    {
        $callback = route('payment.chip.callback');

        return array_filter([
            'success' => route('payment.chip.back', $order),
            'failure' => route('payment.chip.back', [$order, 'result' => 'failed']),
            'cancel' => route('preorder.pay', $order),
            // CHIP rejects callback URLs with an explicit port (e.g. localhost:8000).
            'callback' => parse_url($callback, PHP_URL_PORT) ? null : $callback,
        ]);
    }
}
