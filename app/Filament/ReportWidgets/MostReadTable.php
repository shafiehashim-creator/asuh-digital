<?php

namespace App\Filament\ReportWidgets;

class MostReadTable extends ReportTable
{
    protected function heading(): string
    {
        return 'Buku paling banyak dibaca (Mod Anak)';
    }

    protected function columns(): array
    {
        return ['book' => 'Buku', 'readers' => 'Anak membaca', 'minutes' => 'Minit'];
    }

    protected function rows(): iterable
    {
        return $this->reports()->mostRead($this->period());
    }
}
