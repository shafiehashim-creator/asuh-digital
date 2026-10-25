<?php

namespace App\Filament\ReportWidgets;

class ProductsTable extends ReportTable
{
    protected function heading(): string
    {
        return 'Produk terjual';
    }

    protected function columns(): array
    {
        return ['product' => 'Produk', 'units' => 'Unit', 'revenue_rm' => 'Jualan', 'share_pct' => 'Bahagian'];
    }

    protected function rows(): iterable
    {
        return $this->reports()->products($this->period())
            ->map(fn ($r) => ['product' => $r['product'], 'units' => $r['units'], 'revenue_rm' => $r['revenue'], 'share_pct' => $r['share']]);
    }
}
