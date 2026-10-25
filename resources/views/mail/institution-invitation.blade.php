<x-mail::message>
# Assalamualaikum, {{ $invitation->name }}

Anda dijemput menyertai ASUH Digital sebagai **{{ $invitation->role->getLabel() }}** untuk **{{ $institution->name }}**.

Melalui ASUH Digital, murid anda boleh membaca e-book kanak-kanak Islamik, menjawab kuiz dan mengumpul lencana. Guru boleh mencipta kelas dan melihat kemajuan bacaan murid.

<x-mail::button :url="$url">
Terima jemputan
</x-mail::button>

Pautan ini sah selama {{ $days }} hari. Jika anda tidak menjangka e-mel ini, abaikan sahaja.

Salam hormat,<br>
Pasukan ASUH Digital
</x-mail::message>
