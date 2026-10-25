<?php

namespace App\Filament\ReportWidgets;

class TopPagesTable extends ReportTable
{
    protected function heading(): string
    {
        return 'Halaman paling dilawati';
    }

    protected function columns(): array
    {
        return ['path' => 'Halaman', 'views' => 'Paparan', 'visitors' => 'Pelawat'];
    }

    protected function rows(): iterable
    {
        return $this->reports()->topPages($this->period());
    }
}
