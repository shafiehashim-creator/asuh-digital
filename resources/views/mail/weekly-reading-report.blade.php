<x-mail::message>
# Assalamualaikum, {{ $parent->name }}

Ini ringkasan bacaan anak anda di ASUH Digital untuk minggu **{{ $weekStart->translatedFormat('j M') }} hingga {{ $weekEnd->translatedFormat('j M Y') }}**.

@foreach ($children as $report)
## {{ $report['child']->nickname }}

<x-mail::panel>
**{{ $report['minutes'] }} minit** membaca, pada **{{ $report['days'] }} hari** minggu ini.
@if ($report['video_minutes'])
<br>**{{ $report['video_minutes'] }} minit** menonton video animasi.
@endif
</x-mail::panel>

@if ($report['books'])
**Buku yang dibaca:** {{ implode(', ', $report['books']) }}
@endif

@if ($report['quizzes'])
**Kuiz lulus:** {{ implode(', ', $report['quizzes']) }}
@endif

@if ($report['badges'])
**Lencana baharu:** {{ implode(', ', array_map(fn ($badge) => $badge->emoji().' '.$badge->label(), $report['badges'])) }}
@endif

@endforeach
Teruskan galakan membaca di rumah. Jom Jadi Baik!

<x-mail::button :url="route('account')">
Buka Akaun saya
</x-mail::button>

Salam hormat,<br>
Pasukan ASUH Digital

<small>Tidak mahu menerima laporan ini lagi? [Berhenti langganan laporan mingguan]({{ $unsubscribeUrl }}).</small>
</x-mail::message>
