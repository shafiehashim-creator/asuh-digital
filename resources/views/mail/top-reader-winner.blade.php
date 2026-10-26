<x-mail::message>
# Tahniah, {{ $winner->child->user->name }}!

**{{ $winner->child->nickname }}** ialah antara **5 pembaca terbaik ASUH** bagi bulan **{{ $winner->month->translatedFormat('F Y') }}** untuk kumpulan umur {{ $winner->age_group->getLabel() }} (tempat ke-{{ $winner->rank }}).

<x-mail::panel>
{{ $winner->minutes }} minit membaca · {{ $winner->days }} hari membaca · {{ $winner->quizzes }} kuiz lulus
</x-mail::panel>

{{ $winner->child->nickname }} juga mendapat lencana **👑 Pembaca Terbaik ASUH** dalam Mod Anak. Pasukan ASUH akan menghubungi anda tidak lama lagi tentang ganjaran.

Terima kasih kerana menggalakkan anak membaca. Jom Jadi Baik!

ASUH Digital
</x-mail::message>
