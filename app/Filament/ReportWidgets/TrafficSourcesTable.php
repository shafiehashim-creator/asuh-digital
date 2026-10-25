<?php

namespace App\Filament\ReportWidgets;

class TrafficSourcesTable extends ReportTable
{
    protected function heading(): string
    {
        return 'Sumber trafik';
    }

    protected function columns(): array
    {
        return ['channel' => 'Saluran', 'visitors' => 'Pelawat', 'orders' => 'Pesanan dibayar', 'revenue_rm' => 'Jualan'];
    }

    protected function rows(): iterable
    {
        return $this->reports()->trafficSources($this->period())
            ->map(fn ($r) => ['channel' => $r['channel'], 'visitors' => $r['visitors'], 'orders' => $r['orders'], 'revenue_rm' => $r['revenue']]);
    }
}
