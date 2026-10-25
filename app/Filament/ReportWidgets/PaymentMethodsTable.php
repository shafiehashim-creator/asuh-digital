<?php

namespace App\Filament\ReportWidgets;

class PaymentMethodsTable extends ReportTable
{
    protected function heading(): string
    {
        return 'Kaedah bayaran';
    }

    protected function columns(): array
    {
        return ['method' => 'Kaedah', 'orders' => 'Pesanan', 'revenue_rm' => 'Jualan'];
    }

    protected function rows(): iterable
    {
        return $this->reports()->paymentMethods($this->period())
            ->map(fn ($r) => ['method' => $r['method'], 'orders' => $r['orders'], 'revenue_rm' => $r['revenue']]);
    }
}
