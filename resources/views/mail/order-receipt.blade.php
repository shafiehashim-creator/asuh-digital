@php use App\Support\Money; @endphp
<x-mail::message>
# {{ __('Terima kasih, :name!', ['name' => $order->user->name]) }}

{{ __('Bayaran anda telah diterima. Ini resit untuk rekod anda.') }}

<x-mail::table>
| | |
|:--|--:|
| **{{ __('No. resit / rujukan') }}** | {{ $order->reference }} |
| **{{ __('Tarikh bayaran') }}** | {{ $order->paid_at?->translatedFormat('j F Y, g:i A') }} |
| **{{ __('Item') }}** | {{ $order->itemName() }} |
| **{{ __('Kaedah bayaran') }}** | {{ $order->chip_method?->label() ?? $order->payment_method->getLabel() }} |
@if ($order->discount_sen > 0)
| **{{ $order->discountLabel() }}** | −{{ Money::format($order->discount_sen) }} |
@endif
@if ($order->processing_fee_sen > 0)
| **{{ __('Harga') }}** | {{ Money::format($order->amount_sen) }} |
| **{{ __('Caj pemprosesan') }}** | {{ Money::format($order->processing_fee_sen) }} |
@endif
| **{{ __('Jumlah dibayar') }}** | **{{ Money::format($order->totalSen()) }}** |
</x-mail::table>

@if ($pass?->starts_at)
{{ __('Langganan anda aktif dari :start hingga :end.', ['start' => $pass->starts_at->translatedFormat('j F Y'), 'end' => $pass->ends_at->translatedFormat('j F Y')]) }}
@elseif ($pass)
{{ __('Langganan anda akan bermula pada tarikh platform ASUH Digital dilancarkan. Kami akan maklumkan tarikhnya melalui e-mel.') }}
@endif

{{ __('Bayaran ini dibuat sekali sahaja. Tiada caj automatik akan dikenakan.') }}

<x-mail::button :url="route('account.billing')">
{{ __('Lihat akaun saya') }}
</x-mail::button>

{{ __('Salam hormat,') }}<br>
{{ __('Pasukan ASUH Digital') }}

@if (array_filter($company))
<x-mail::subcopy>
@if ($company['name']){{ $company['name'] }}@if ($company['ssm']) ({{ $company['ssm'] }})@endif<br>@endif
@if ($company['address']){!! nl2br(e($company['address'])) !!}<br>@endif
@if ($company['sst']){{ __('No. SST') }}: {{ $company['sst'] }}<br>@endif
@if ($company['email']){{ __('Pertanyaan') }}: {{ $company['email'] }}@endif
</x-mail::subcopy>
@endif

</x-mail::message>
