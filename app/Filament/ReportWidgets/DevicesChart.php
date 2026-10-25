<?php

namespace App\Filament\ReportWidgets;

use App\Filament\ReportWidgets\Concerns\UsesReportPeriod;
use Filament\Widgets\ChartWidget;

class DevicesChart extends ChartWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Peranti pelawat';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $devices = $this->reports()->devices($this->period());

        return [
            'labels' => array_map('ucfirst', array_keys($devices)),
            'datasets' => [[
                'data' => array_values($devices),
                'backgroundColor' => array_slice(ChannelsChart::PALETTE, 0, max(1, count($devices))),
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
