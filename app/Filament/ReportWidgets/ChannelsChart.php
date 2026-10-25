<?php

namespace App\Filament\ReportWidgets;

use App\Filament\ReportWidgets\Concerns\UsesReportPeriod;
use Filament\Widgets\ChartWidget;

class ChannelsChart extends ChartWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Saluran jualan (RM)';

    protected ?string $maxHeight = '280px';

    public const PALETTE = ['#3A1F6E', '#C4107A', '#F79521', '#1559D6', '#FFDE37', '#2E9E6B', '#8A8A8A', '#231F20'];

    protected function getData(): array
    {
        $rows = $this->reports()->channels($this->period());

        return [
            'labels' => $rows->pluck('channel')->all(),
            'datasets' => [[
                'data' => $rows->map(fn ($r) => round($r['revenue'] / 100, 2))->all(),
                'backgroundColor' => array_slice(self::PALETTE, 0, max(1, $rows->count())),
            ]],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
