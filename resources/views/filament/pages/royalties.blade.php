@php
    use App\Support\Money;

    $report = $this->report();
    $cell = 'padding: 0.5rem 0.75rem 0.5rem 0; border-bottom: 1px solid rgba(128,128,128,.12);';
    $num = $cell.' text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap;';
    $head = 'padding: 0.5rem 0.75rem 0.5rem 0; font-weight: 600; opacity: .75; white-space: nowrap; border-bottom: 1px solid rgba(128,128,128,.3);';
@endphp

{{-- Inline styles: Filament's stylesheet does not include the site's Tailwind classes. --}}
<x-filament-panels::page>
    <x-filament::section>
        <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: end;">
            <label style="display: grid; gap: .25rem; font-size: .875rem; font-weight: 600;">
                Bulan
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="month">
                        @foreach ($this->monthOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label style="display: grid; gap: .25rem; font-size: .875rem; font-weight: 600;">
                Kolam royalti (% daripada pendapatan)
                <x-filament::input.wrapper suffix="%">
                    <x-filament::input type="number" min="0" max="100" step="0.5" wire:model.live.debounce.500ms="poolPercent" />
                </x-filament::input.wrapper>
            </label>
        </div>
        <p style="margin-top: .75rem; font-size: .8125rem; opacity: .75; max-width: 60rem;">
            Pendapatan langganan diagihkan ikut hari: RM99 setahun dikira lebih kurang RM8.25 sebulan, bukan RM99 pada bulan dibayar. Untuk Langganan Plus, hanya bahagian e-book dikira.
            Satu bacaan = seorang anak keluarga membaca buku itu sekurang-kurangnya {{ App\Models\Setting::get('royalty_min_minutes') }} minit dalam bulan tersebut (murid lesen sekolah tidak dikira).
            Jualan e-book single: {{ rtrim(rtrim(number_format((float) App\Models\Setting::get('royalty_single_percent'), 2), '0'), '.') }}% daripada harga. Kadar lalai diubah di Tetapan → Royalti; kadar khas setiap buku di halaman buku.
        </p>
    </x-filament::section>

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));">
        @foreach ([
            ['Pendapatan langganan bulan ini', Money::format($report['revenue_sen'])],
            ['Kolam royalti ('.rtrim(rtrim(number_format($report['pool_percent'], 2), '0'), '.').'%)', Money::format($report['pool_sen'])],
            ['Jumlah bacaan', number_format($report['reads'])],
            ['Jumlah royalti', Money::format((int) $report['books']->sum('total_sen'))],
        ] as [$label, $value])
            <x-filament::section>
                <div style="font-size: .875rem; opacity: .75;">{{ $label }}</div>
                <div style="font-size: 1.5rem; font-weight: 700; margin-top: .25rem;">{{ $value }}</div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Ikut buku">
        @if ($report['books']->isEmpty())
            <p style="font-size: .875rem; opacity: .7;">Tiada bacaan atau jualan single pada bulan ini.</p>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                    <thead><tr>
                        <th style="{{ $head }} text-align: left;">Buku</th>
                        <th style="{{ $head }} text-align: right;">Bacaan</th>
                        <th style="{{ $head }} text-align: right;">Bahagian</th>
                        <th style="{{ $head }} text-align: right;">Royalti langganan</th>
                        <th style="{{ $head }} text-align: right;">Jualan single</th>
                        <th style="{{ $head }} text-align: right;">Royalti single</th>
                        <th style="{{ $head }} text-align: right;">Jumlah</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($report['books'] as $row)
                            <tr>
                                <td style="{{ $cell }}">
                                    <a href="{{ App\Filament\Resources\Books\BookResource::getUrl('edit', ['record' => $row['book']]) }}" style="font-weight: 500;">{{ $row['book']->title }}</a>
                                    <div style="font-size: .75rem; opacity: .7;">
                                        @forelse ($row['book']->contributors as $c)
                                            {{ $c->name }} {{ rtrim(rtrim(number_format($c->pivot->share_percent, 2), '0'), '.') }}%@if (! $loop->last), @endif
                                        @empty
                                            {{ $row['book']->author }}
                                        @endforelse
                                    </div>
                                    @unless ($row['shares_ok'])
                                        <div style="font-size: .75rem; font-weight: 600; color: rgb(217 119 6);">
                                            {{ $row['book']->contributors->isEmpty() ? 'Belum ada penerima royalti untuk buku ini.' : 'Bahagian penerima tidak berjumlah 100%.' }}
                                        </div>
                                    @endunless
                                </td>
                                <td style="{{ $num }}">{{ number_format($row['reads']) }}</td>
                                <td style="{{ $num }}">{{ number_format($row['share'], 1) }}%</td>
                                <td style="{{ $num }}">{{ Money::format($row['pool_sen']) }}</td>
                                <td style="{{ $num }}">{{ Money::format($row['single_sales_sen']) }}</td>
                                <td style="{{ $num }}">
                                    {{ Money::format($row['single_sen']) }}
                                    @if ($row['single_sales_sen'] > 0)
                                        <div style="font-size: .75rem; opacity: .7;">{{ rtrim(rtrim(number_format($row['single_rate'], 2), '0'), '.') }}%@if ($row['book']->royalty_single_percent !== null) (khas)@endif</div>
                                    @endif
                                </td>
                                <td style="{{ $num }} font-weight: 700;">{{ Money::format($row['total_sen']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Ikut penerima (untuk pembayaran)">
        @if ($report['contributors']->isEmpty())
            <p style="font-size: .875rem; opacity: .7;">Tiada penerima. Tetapkan penulis dan ilustrator setiap buku di Buku → Penerima royalti.</p>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                    <thead><tr>
                        <th style="{{ $head }} text-align: left;">Nama</th>
                        <th style="{{ $head }} text-align: left;">Peranan</th>
                        <th style="{{ $head }} text-align: left;">Akaun bank</th>
                        <th style="{{ $head }} text-align: right;">Buku</th>
                        <th style="{{ $head }} text-align: right;">Royalti</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($report['contributors'] as $row)
                            <tr>
                                <td style="{{ $cell }} font-weight: 500;">{{ $row['name'] }}</td>
                                <td style="{{ $cell }}">{{ $row['role'] }}</td>
                                <td style="{{ $cell }}">{{ $row['bank'] ?: '-' }}</td>
                                <td style="{{ $num }}">{{ $row['books'] }}</td>
                                <td style="{{ $num }} font-weight: 700;">{{ Money::format($row['total_sen']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
