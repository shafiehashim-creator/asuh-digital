@php use App\Support\MarkdownText; @endphp
<x-mail::message>
# Permohonan pilot baharu

<x-mail::table>
| | |
|:--|:--|
| **Institusi** | {{ MarkdownText::escape($pilot->institution_name) }} |
| **Jenis** | {{ $pilot->institution_type->getLabel() }} |
| **Lokasi** | {{ MarkdownText::escape($pilot->district) }}, {{ MarkdownText::escape($pilot->state) }} |
| **Murid** | {{ $pilot->student_count }} ({{ $pilot->age_group->getLabel() }}) |
| **Pegawai** | {{ MarkdownText::escape($pilot->contact_name) }}, {{ MarkdownText::escape($pilot->contact_position) }} |
| **E-mel** | {{ MarkdownText::escape($pilot->email) }} |
| **Telefon** | {{ MarkdownText::escape($pilot->phone) }} |
</x-mail::table>

@if ($pilot->notes)
**Catatan:** {{ MarkdownText::escape($pilot->notes) }}
@endif

<x-mail::button :url="$url">
Buka permohonan
</x-mail::button>
</x-mail::message>
