@php
    use App\Services\TopReaders;

    $month = $this->selectedMonth();
    $winners = $this->winners();
    $cell = 'padding: 0.5rem 0.75rem 0.5rem 0; border-bottom: 1px solid rgba(128,128,128,.12);';
    $num = $cell.' text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap;';
    $head = 'padding: 0.5rem 0.75rem 0.5rem 0; font-weight: 600; opacity: .75; white-space: nowrap; border-bottom: 1px solid rgba(128,128,128,.3);';
@endphp

{{-- Inline styles: Filament's stylesheet does not include the site's Tailwind classes. --}}
<x-filament-panels::page>
    <x-filament::section>
        <label style="display: grid; gap: .25rem; font-size: .875rem; font-weight: 600; max-width: 16rem;">
            Bulan
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="month">
                    @foreach ($this->monthOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
        <p style="margin-top: .75rem; font-size: .8125rem; opacity: .75; max-width: 60rem;">
            Mata = minit membaca (maksimum {{ TopReaders::MAX_MINUTES_PER_DAY }} sehari dikira) + {{ TopReaders::POINTS_PER_DAY }} setiap hari membaca (sekurang-kurangnya 5 minit) + {{ TopReaders::POINTS_PER_QUIZ }} setiap kuiz buku yang lulus kali pertama bulan itu.
            Hanya anak keluarga yang ibu bapanya melanggan pada bulan itu; murid lesen sekolah tidak dikira. Senarai ini sulit: jangan kongsi nama anak di laman awam.
            @if ($month->copy()->endOfMonth()->isFuture())
                <strong>Bulan ini belum tamat: ranking masih berubah, dan pemenang boleh diumumkan selepas bulan tamat.</strong>
            @endif
        </p>
    </x-filament::section>

    @if ($winners->isNotEmpty())
        <x-filament::section heading="Pemenang yang diumumkan">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                    <thead><tr>
                        <th style="{{ $head }} text-align: left;">Umur</th>
                        <th style="{{ $head }} text-align: right;">Tempat</th>
                        <th style="{{ $head }} text-align: left;">Anak</th>
                        <th style="{{ $head }} text-align: left;">Ibu bapa</th>
                        <th style="{{ $head }} text-align: right;">Mata</th>
                        <th style="{{ $head }} text-align: left;">Ganjaran</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($winners as $winner)
                            <tr>
                                <td style="{{ $cell }}">{{ $winner->age_group->getLabel() }}</td>
                                <td style="{{ $num }}">{{ $winner->rank }}</td>
                                <td style="{{ $cell }} font-weight: 600;">{{ $winner->child?->nickname }}</td>
                                <td style="{{ $cell }}">{{ $winner->child?->user?->name }}<br><span style="opacity: .7;">{{ $winner->child?->user?->email }} · {{ $winner->child?->user?->phone }}</span></td>
                                <td style="{{ $num }}">{{ number_format($winner->points) }}</td>
                                <td style="{{ $cell }}">
                                    <x-filament::button size="xs" :color="$winner->reward_sent_at ? 'success' : 'gray'" wire:click="markRewardSent({{ $winner->id }})">
                                        {{ $winner->reward_sent_at ? '✓ Dihantar '.$winner->reward_sent_at->translatedFormat('j M') : 'Tandakan dihantar' }}
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    @foreach ($this->rankings() as $label => $rows)
        <x-filament::section :heading="'Ranking umur '.$label">
            @if ($rows->isEmpty())
                <p style="font-size: .875rem; opacity: .7;">Tiada bacaan daripada anak yang layak pada bulan ini.</p>
            @else
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                        <thead><tr>
                            <th style="{{ $head }} text-align: right;">#</th>
                            <th style="{{ $head }} text-align: left;">Anak</th>
                            <th style="{{ $head }} text-align: left;">Ibu bapa</th>
                            <th style="{{ $head }} text-align: right;">Minit</th>
                            <th style="{{ $head }} text-align: right;">Hari</th>
                            <th style="{{ $head }} text-align: right;">Kuiz</th>
                            <th style="{{ $head }} text-align: right;">Mata</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                <tr @if ($i < TopReaders::WINNERS) style="background: rgba(255, 222, 55, .15);" @endif>
                                    <td style="{{ $num }}">{{ $i + 1 }}</td>
                                    <td style="{{ $cell }} font-weight: 600;">{{ $row['child']->nickname }}</td>
                                    <td style="{{ $cell }}">{{ $row['child']->user?->name }}</td>
                                    <td style="{{ $num }}">{{ $row['minutes'] }}</td>
                                    <td style="{{ $num }}">{{ $row['days'] }}</td>
                                    <td style="{{ $num }}">{{ $row['quizzes'] }}</td>
                                    <td style="{{ $num }} font-weight: 700;">{{ number_format($row['points']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
