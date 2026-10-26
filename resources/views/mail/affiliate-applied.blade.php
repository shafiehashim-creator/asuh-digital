@php use App\Support\MarkdownText; @endphp
<x-mail::message>
# Permohonan affiliate baharu

<x-mail::table>
| | |
|:--|:--|
| **Nama** | {{ MarkdownText::escape($affiliate->user->name) }} |
| **E-mel** | {{ MarkdownText::escape($affiliate->user->email) }} |
| **Telefon** | {{ MarkdownText::escape((string) $affiliate->user->phone) }} |
</x-mail::table>

**Cara mempromosi:** {{ MarkdownText::escape((string) $affiliate->promotion_channels) }}

<x-mail::button :url="$url">
Semak di panel admin
</x-mail::button>
</x-mail::message>
