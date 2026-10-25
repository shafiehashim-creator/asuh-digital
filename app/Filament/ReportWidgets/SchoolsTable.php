<?php

namespace App\Filament\ReportWidgets;

class SchoolsTable extends ReportTable
{
    protected function heading(): string
    {
        return 'Jualan keluarga melalui kod sekolah';
    }

    protected function columns(): array
    {
        return ['school' => 'Institusi', 'orders' => 'Pesanan', 'revenue_rm' => 'Jualan', 'discounts_rm' => 'Diskaun'];
    }

    protected function rows(): iterable
    {
        return $this->reports()->schools($this->period())
            ->map(fn ($r) => ['school' => $r['school'], 'orders' => $r['orders'], 'revenue_rm' => $r['revenue'], 'discounts_rm' => $r['discounts']]);
    }
}
