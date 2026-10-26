<?php

namespace App\Services\Chip;

use App\Enums\ChipMethod;
use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Thin wrapper around the CHIP Collect API.
 *
 * @see https://docs.chip-in.asia/chip-collect/api-reference
 */
class ChipClient
{
    /** Statuses where the buyer can still pay on the existing checkout page. */
    public const PAYABLE_STATUSES = ['created', 'sent', 'viewed', 'overdue'];

    public function isConfigured(): bool
    {
        return filled(config('services.chip.brand_id')) && filled(config('services.chip.secret_key'));
    }

    /**
     * @return array<string, mixed> the CHIP Purchase object
     */
    public function createPurchase(Order $order, array $urls): array
    {
        $user = $order->user;

        $client = array_filter([
            'email' => $user->email,
            'full_name' => $user->name,
            'phone' => $this->formatPhone($user->phone),
        ]);

        $payload = array_filter([
            'brand_id' => config('services.chip.brand_id'),
            'reference' => $order->reference,
            'client' => $client,
            'purchase' => [
                'currency' => $order->currency,
                'products' => array_values(array_filter([
                    [
                        'name' => $order->itemName(),
                        // CHIP prices are in cents (sen), like amount_sen.
                        'price' => $order->amount_sen,
                        'quantity' => 1,
                    ],
                    $order->processing_fee_sen > 0 ? [
                        'name' => 'Caj pemprosesan '.$order->chip_method?->label(),
                        'price' => $order->processing_fee_sen,
                        'quantity' => 1,
                    ] : null,
                ])),
            ],
            // Only the method the buyer chose on our pay page, so its fee is right.
            'payment_method_whitelist' => $order->chip_method ? $this->whitelist($order->chip_method) : null,
            'success_redirect' => $urls['success'] ?? null,
            'failure_redirect' => $urls['failure'] ?? null,
            'cancel_redirect' => $urls['cancel'] ?? null,
            'success_callback' => $urls['callback'] ?? null,
            'send_receipt' => false,
        ], fn ($value) => $value !== null);

        return $this->request()->post('purchases/', $payload)->throw()->json();
    }

    /**
     * @return array<string, mixed> the CHIP Purchase object
     */
    public function getPurchase(string $id): array
    {
        return $this->request()->retry(2, 500, throw: false)
            ->get('purchases/'.rawurlencode($id).'/')->throw()->json();
    }

    /**
     * Cancel an unpaid purchase so its checkout page can no longer be paid,
     * before a new one is made for a different method.
     */
    public function cancelPurchase(string $id): void
    {
        $this->request()->post('purchases/'.rawurlencode($id).'/cancel/')->throw();
    }

    /**
     * Payment method codes the brand has switched on, cached for an hour.
     * Null when CHIP cannot be asked; the whitelist then uses our own list.
     *
     * @return list<string>|null
     */
    public function availableMethods(): ?array
    {
        $key = 'chip.payment_methods.'.config('services.chip.brand_id');
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached ?: null; // false: CHIP could not be asked a moment ago
        }

        try {
            $methods = $this->request()->timeout(5)
                ->get('payment_methods/', ['brand_id' => config('services.chip.brand_id'), 'currency' => 'MYR'])
                ->throw()->json('available_payment_methods');

            if (! is_array($methods) || $methods === []) {
                throw new RuntimeException('Unexpected CHIP payment methods response.');
            }

            Cache::put($key, array_values($methods), now()->addHour());

            return array_values($methods);
        } catch (Throwable) {
            // Do not make every pay page wait on CHIP while it is unreachable.
            Cache::put($key, false, now()->addMinutes(5));

            return null;
        }
    }

    /**
     * Which methods the buyer can pick on the pay page: those the brand has on,
     * or all when CHIP cannot be asked.
     *
     * @return list<ChipMethod>
     */
    public function offeredMethods(): array
    {
        $available = $this->availableMethods();

        return array_values(array_filter(
            ChipMethod::cases(),
            fn (ChipMethod $method) => $available === null || array_intersect($method->chipCodes(), $available) !== [],
        ));
    }

    /** @return list<string> */
    private function whitelist(ChipMethod $method): array
    {
        $available = $this->availableMethods();

        return $available === null
            ? $method->chipCodes()
            : array_values(array_intersect($method->chipCodes(), $available));
    }

    /**
     * Verify the X-Signature header: base64 RSA PKCS#1 v1.5 over the SHA-256
     * of the raw body. Success callbacks are signed with the company key;
     * portal webhooks with their own key, which may be set in config.
     */
    public function verifySignature(string $rawBody, ?string $signature): bool
    {
        $decoded = $signature ? base64_decode($signature, true) : false;

        if ($decoded === false || $decoded === '') {
            return false;
        }

        foreach (array_filter([$this->companyPublicKey(), config('services.chip.webhook_public_key')]) as $pem) {
            if (openssl_verify($rawBody, $decoded, $pem, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }

    public function companyPublicKey(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return Cache::remember('chip.public_key', now()->addDay(), function () {
            $response = $this->request()->retry(2, 500, throw: false)->get('public_key/')->throw();

            // The endpoint returns the PEM as a JSON-encoded string.
            $pem = json_decode($response->body(), true);

            if (! is_string($pem) || ! str_contains($pem, 'PUBLIC KEY')) {
                throw new RuntimeException('Unexpected CHIP public key response.');
            }

            return $pem;
        });
    }

    /**
     * "0123456789" => "+60 123456789", the format CHIP documents.
     */
    private function formatPhone(?string $phone): ?string
    {
        return $phone && str_starts_with($phone, '0') ? '+60 '.substr($phone, 1) : $phone;
    }

    /**
     * No automatic retry here: retrying POST purchases/ after a lost response
     * could create a second purchase. Read-only calls add their own retry.
     */
    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('services.chip.base_url'), '/').'/')
            ->withToken(config('services.chip.secret_key'))
            ->acceptJson()
            ->timeout(15);
    }
}
