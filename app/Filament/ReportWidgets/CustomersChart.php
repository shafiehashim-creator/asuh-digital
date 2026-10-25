<?php

namespace App\Filament\ReportWidgets;

use App\Filament\ReportWidgets\Concerns\UsesReportPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class CustomersChart extends ChartWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Akaun baharu dan pembeli baharu';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $data = $this->reports()->dailyCustomers($this->period());

        return [
            'labels' => array_map(fn ($d) => Carbon::parse($d)->translatedFormat('j M'), $data['labels']),
            'datasets' => [
                ['label' => 'Akaun baharu', 'data' => $data['accounts'], 'borderColor' => '#1559D6', 'backgroundColor' => '#1559D6'],
                ['label' => 'Pembeli kali pertama', 'data' => $data['buyers'], 'borderColor' => '#C4107A', 'backgroundColor' => '#C4107A'],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
