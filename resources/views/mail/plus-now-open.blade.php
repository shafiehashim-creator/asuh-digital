<x-mail::message>
# {{ __('Langganan Plus kini dibuka!') }}

{{ __('Anda meminta kami memberitahu anda. Langganan Plus ASUH Digital kini boleh dilanggan: semua e-book ASUH, ditambah semua video animasi, untuk seisi keluarga.') }}

{{ __('Sudah ada Langganan Tahunan? Naik taraf dan bayar beza harga untuk baki hari sahaja.') }}

<x-mail::button :url="route('plus.show')">
{{ __('Lihat Langganan Plus') }}
</x-mail::button>

{{ __('Jom Jadi Baik!') }}

ASUH Digital
</x-mail::message>
