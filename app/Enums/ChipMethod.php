<?php

namespace App\Enums;

/**
 * How the buyer pays through CHIP, chosen on our pay page so the processing
 * fee is known before the CHIP purchase is created.
 */
enum ChipMethod: string
{
    case Fpx = 'fpx';
    case Ewallet = 'ewallet';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Fpx => __('FPX (perbankan dalam talian)'),
            self::Ewallet => __('E-wallet dan DuitNow QR'),
            self::Card => __('Kad debit atau kredit'),
        };
    }

    /** CHIP payment method codes for payment_method_whitelist. */
    public function chipCodes(): array
    {
        return match ($this) {
            self::Fpx => ['fpx', 'fpx_b2b1'],
            self::Ewallet => ['razer_tng', 'razer_grabpay', 'razer_shopeepay', 'razer_maybankqr', 'duitnow_qr'],
            self::Card => ['visa', 'mastercard', 'maestro'],
        };
    }

    /**
     * Card payments never carry a fee: merchants in Malaysia may not
     * surcharge cardholders (Bank Negara Malaysia, card scheme rules).
     */
    public function mayCarryFee(): bool
    {
        return $this !== self::Card;
    }
}
