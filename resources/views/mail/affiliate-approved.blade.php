<x-mail::message>
# Tahniah, {{ App\Support\MarkdownText::escape($affiliate->user->name) }}!

Permohonan affiliate anda telah diluluskan. Kongsi pautan atau kod anda, dan dapatkan komisen bagi setiap jualan.

**Pautan:** {{ $affiliate->link() }}<br>
**Kod:** {{ $affiliate->code }}

<x-mail::button :url="$url">
Lihat halaman affiliate
</x-mail::button>

Jom Jadi Baik!<br>
ASUH Digital
</x-mail::message>
