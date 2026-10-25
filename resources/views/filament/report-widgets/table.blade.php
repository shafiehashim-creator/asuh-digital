{{-- Inline styles: Filament's stylesheet does not include the site's Tailwind classes. --}}
<x-filament-widgets::widget>
    <x-filament::section :heading="$heading">
        @if (empty($rows))
            <p style="font-size: 0.875rem; opacity: 0.7;">{{ $emptyText }}</p>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(128, 128, 128, 0.3);">
                            @foreach ($columns as $key => $label)
                                <th scope="col" style="padding: 0.5rem 0.75rem 0.5rem 0; font-weight: 600; opacity: 0.75; white-space: nowrap; text-align: {{ $loop->first ? 'left' : 'right' }};">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr style="border-bottom: 1px solid rgba(128, 128, 128, 0.12);">
                                @foreach ($columns as $key => $label)
                                    @php($value = $row[$key] ?? null)
                                    <td style="padding: 0.5rem 0.75rem 0.5rem 0; text-align: {{ $loop->first ? 'left' : 'right' }}; {{ $loop->first ? 'font-weight: 500;' : 'font-variant-numeric: tabular-nums; white-space: nowrap;' }}">
                                        @if (str_ends_with($key, '_rm'))
                                            {{ App\Support\Money::format((int) $value) }}
                                        @elseif (str_ends_with($key, '_pct'))
                                            {{ $value === null ? '-' : number_format((float) $value, 1).'%' }}
                                        @else
                                            {{ is_numeric($value) ? number_format($value) : $value }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
