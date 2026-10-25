<?php

namespace App\Filament\ReportWidgets;

use App\Filament\ReportWidgets\Concerns\UsesReportPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class SalesChart extends ChartWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Graf jualan harian (RM)';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    private const COLOURS = ['Langganan' => '#3A1F6E', 'Buku single' => '#C4107A', 'Naik taraf' => '#F79521'];

    protected function getData(): array
    {
        $data = $this->reports()->dailySales($this->period());

        return [
            'labels' => array_map(fn ($d) => Carbon::parse($d)->translatedFormat('j M'), $data['labels']),
            'datasets' => collect($data['series'])->map(fn ($values, $name) => [
                'label' => $name,
                'data' => array_map(fn ($sen) => round($sen / 100, 2), $values),
                'backgroundColor' => self::COLOURS[$name],
                'borderColor' => self::COLOURS[$name],
                'stack' => 'jualan',
            ])->values()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true]]];
    }
}
