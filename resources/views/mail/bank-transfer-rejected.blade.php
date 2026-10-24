<x-mail::message>
# Pindahan anda belum dapat disahkan

Assalamualaikum {{ $order->user->name }},

Kami tidak dapat mengesahkan pindahan bank untuk pesanan **{{ $order->reference }}** berdasarkan resit yang dihantar. Contohnya, jumlah atau rujukan tidak sepadan, atau resit kurang jelas.

Jika anda sudah membuat pindahan, sila hantar semula resit yang jelas menunjukkan tarikh, jumlah dan rujukan pesanan.

<x-mail::button :url="route('preorder.pay', $order)">
Hantar semula resit
</x-mail::button>

@if ($contactEmail)
Ada pertanyaan? Balas kepada {{ $contactEmail }}.
@endif

Salam hormat,<br>
Pasukan ASUH Digital
</x-mail::message>
