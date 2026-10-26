<x-mail::message>
# Assalamualaikum, {{ $promise->child->user->name }}

Alhamdulillah, **{{ $promise->child->nickname }}** baru sahaja mendapat lencana **{{ $promise->badge->emoji() }} {{ $promise->badge->label() }}** di ASUH Digital.

<x-mail::panel>
Janji anda: **{{ $promise->reward }}**
</x-mail::panel>

Tunaikan janji itu bila sempat, kemudian tandakan **Sudah ditunaikan** di halaman Pencapaian supaya {{ $promise->child->nickname }} nampak janji anda ditepati.

<x-mail::button :url="route('account.progress')">
Buka Pencapaian
</x-mail::button>

Jom Jadi Baik!<br>
ASUH Digital
</x-mail::message>
