@php use App\Support\Money; @endphp
<x-mail::message>
# Resit pindahan bank menunggu pengesahan

**{{ $order->user->name }}** ({{ $order->user->email }}) telah menghantar resit untuk pesanan **{{ $order->reference }}** berjumlah **{{ Money::format($order->amount_sen) }}**.

Semak akaun bank, kemudian sahkan atau tolak bayaran ini di panel admin.

<x-mail::button :url="$url">
Buka pesanan
</x-mail::button>
</x-mail::message>
