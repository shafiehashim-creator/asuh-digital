<?php

namespace App\Filament\ReportWidgets;

class StatesTable extends ReportTable
{
    protected function heading(): string
    {
        return 'Pelanggan ikut negeri (daripada borang pre-order)';
    }

    protected function columns(): array
    {
        return ['state' => 'Negeri', 'customers' => 'Keluarga'];
    }

    protected function rows(): iterable
    {
        return $this->reports()->states();
    }
}
