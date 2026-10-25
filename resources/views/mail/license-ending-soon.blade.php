<x-mail::message>
# Assalamualaikum

Lesen {{ $license->kind->getLabel() }} ASUH Digital untuk **{{ $institution->name }}** akan tamat pada **{{ $license->ends_on->translatedFormat('j F Y') }}** ({{ $days }} hari lagi).

Selepas tarikh itu, murid tidak dapat membaca melalui kod kelas. Kelas, nama murid dan rekod bacaan tetap disimpan dan akan tersedia semula sebaik lesen diperbaharui.

Untuk memperbaharui, balas e-mel ini@if ($whatsapp) atau hubungi kami melalui WhatsApp di {{ $whatsapp }}@endif.

<x-mail::button :url="route('teacher.home')">
Buka portal guru
</x-mail::button>

Salam hormat,<br>
Pasukan ASUH Digital
</x-mail::message>
