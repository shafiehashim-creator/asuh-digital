<?php

namespace App\Filament\ReportWidgets;

use App\Filament\ReportWidgets\Concerns\UsesReportPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class TrafficChart extends ChartWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Pelawat harian';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $data = $this->reports()->dailyTraffic($this->period());

        return [
            'labels' => array_map(fn ($d) => Carbon::parse($d)->translatedFormat('j M'), $data['labels']),
            'datasets' => [
                ['label' => 'Pelawat unik', 'data' => $data['visitors'], 'borderColor' => '#3A1F6E', 'backgroundColor' => '#3A1F6E'],
                ['label' => 'Paparan halaman', 'data' => $data['views'], 'borderColor' => '#F79521', 'backgroundColor' => '#F79521'],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
