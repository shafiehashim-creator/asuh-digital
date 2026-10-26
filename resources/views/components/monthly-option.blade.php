@props(['product' => 'pass', 'dark' => false, 'align' => 'center'])

{{--
    Under a yearly price: how much the year saves against paying monthly,
    and a quieter link to the monthly option. Shows only after the early
    bird is over and when a monthly price is set (Tetapan).
--}}
@php
    use App\Enums\BillingPeriod;
    use App\Services\SubscriptionPlans;
    use App\Support\Money;

    $plans = app(SubscriptionPlans::class);
    $earlyBird = $product === 'pass' && app(App\Services\PassOffer::class)->earlyBird();
    $yearly = $product === 'plus' ? $plans->plus(BillingPeriod::Yearly) : $plans->pass(BillingPeriod::Yearly);
    $monthly = $product === 'plus' ? $plans->plus(BillingPeriod::Monthly) : $plans->pass(BillingPeriod::Monthly);
    $saving = $yearly && $monthly ? SubscriptionPlans::yearlySavingPercent($yearly, $monthly) : 0;
    $url = $product === 'plus' ? route('purchase.plus', ['tempoh' => 'bulanan']) : route('purchase.pass', ['tempoh' => 'bulanan']);
@endphp

@if ($monthly && ! $earlyBird)
    <div {{ $attributes->class(['text-sm', 'text-center' => $align === 'center', 'text-left' => $align === 'left']) }}>
        @if ($saving > 0)
            <p @class(['font-extrabold', 'text-kuning' => $dark, 'text-biru' => ! $dark])>
                ★ {{ __('Jimat :percent% berbanding bayar bulanan (:monthly × 12 = :total)', ['percent' => $saving, 'monthly' => Money::format($monthly->price_sen), 'total' => Money::format($monthly->price_sen * 12)]) }}
            </p>
        @endif
        <a href="{{ $url }}" @class(['mt-1 inline-block font-bold underline underline-offset-2', 'text-white/85' => $dark, 'text-ink/70' => ! $dark])>
            {{ __('Atau langgan bulanan, :price sebulan', ['price' => Money::short($monthly->price_sen)]) }}
        </a>
    </div>
@endif
