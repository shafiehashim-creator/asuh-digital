@php use App\Support\MarkdownText; use App\Support\Money; @endphp
<x-mail::message>
# Resit pindahan bank menunggu pengesahan

**{{ MarkdownText::escape($order->user->name) }}** ({{ MarkdownText::escape($order->user->email) }}) telah menghantar resit untuk pesanan **{{ $order->reference }}** berjumlah **{{ Money::format($order->amount_sen) }}**.

Semak akaun bank, kemudian sahkan atau tolak bayaran ini di panel admin.

<x-mail::button :url="$url">
Buka pesanan
</x-mail::button>
</x-mail::message>
