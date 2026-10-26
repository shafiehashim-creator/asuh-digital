@php use App\Support\Money; @endphp
<x-mail::message>
# {{ __('Assalamualaikum, :name', ['name' => $pass->user->name]) }}

{{ __(':plan anda tamat pada :date. Tiada caj automatik, jadi akses buku akan ditutup selepas tarikh itu jika tidak diperbaharui.', ['plan' => __($pass->plan->name), 'date' => $pass->ends_at->translatedFormat('j F Y')]) }}

@if ($yearly && $saving > 0)
**{{ __('Tukar ke tahunan dan jimat :percent%.', ['percent' => $saving]) }}** {{ __('Bayar :price sekali untuk 12 bulan. Langganan tahunan bermula sebaik bulan semasa tamat, jadi tiada hari yang hilang.', ['price' => Money::short($yearly->price_sen)]) }}

<x-mail::button :url="$yearlyUrl">
{{ __('Tukar ke tahunan') }}
</x-mail::button>
@endif

{{ __('Atau perbaharui untuk sebulan lagi:') }} [{{ __('Perbaharui :price', ['price' => Money::short($pass->plan->price_sen)]) }}]({{ $monthlyUrl }})

{{ __('Salam hormat,') }}<br>
{{ __('Pasukan ASUH Digital') }}
</x-mail::message>
