@php use App\Support\Money; @endphp
<x-mail::message>
# Terima kasih, {{ $order->user->name }}!

Bayaran anda telah diterima. Ini resit untuk rekod anda.

<x-mail::table>
| | |
|:--|--:|
| **No. resit / rujukan** | {{ $order->reference }} |
| **Tarikh bayaran** | {{ $order->paid_at?->translatedFormat('j F Y, g:i A') }} |
| **Item** | {{ $order->plan?->name ?? 'ASUH Digital' }} |
| **Kaedah bayaran** | {{ $order->payment_method->getLabel() }} |
| **Jumlah dibayar** | **{{ Money::format($order->amount_sen) }}** |
</x-mail::table>

@if ($pass?->starts_at)
Pas Tahunan anda **aktif dari {{ $pass->starts_at->translatedFormat('j F Y') }} hingga {{ $pass->ends_at->translatedFormat('j F Y') }}**.
@elseif ($pass)
Pas Tahunan anda akan **bermula pada tarikh platform ASUH Digital dilancarkan**. Kami akan maklumkan tarikhnya melalui e-mel.
@endif

Bayaran ini dibuat sekali sahaja. Tiada caj automatik akan dikenakan.

<x-mail::button :url="route('account')">
Lihat akaun saya
</x-mail::button>

Salam hormat,<br>
Pasukan ASUH Digital

@if (array_filter($company))
<x-mail::subcopy>
@if ($company['name']){{ $company['name'] }}@if ($company['ssm']) ({{ $company['ssm'] }})@endif<br>@endif
@if ($company['address']){!! nl2br(e($company['address'])) !!}<br>@endif
@if ($company['sst'])No. SST: {{ $company['sst'] }}<br>@endif
@if ($company['email'])Pertanyaan: {{ $company['email'] }}@endif
</x-mail::subcopy>
@endif

</x-mail::message>
