<x-mail::message>
# Terima kasih, {{ $pilot->contact_name }}!

Kami telah menerima permohonan pilot ASUH Digital untuk **{{ $pilot->institution_name }}**. Pasukan kami akan menghubungi anda untuk mengatur pilot percuma selama {{ $pilotDays }} hari.

<x-mail::panel>
**Institusi:** {{ $pilot->institution_name }} ({{ $pilot->institution_type->getLabel() }})<br>
**Lokasi:** {{ $pilot->district }}, {{ $pilot->state }}<br>
**Anggaran murid:** {{ $pilot->student_count }} ({{ $pilot->age_group->getLabel() }})<br>
**Telefon:** {{ $pilot->phone }}
</x-mail::panel>

Jika ada maklumat yang perlu dibetulkan, balas e-mel ini.

Salam hormat,<br>
Pasukan ASUH Digital
</x-mail::message>
