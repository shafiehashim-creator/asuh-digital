<?php

namespace App\Filament\ReportWidgets;

class ChannelsTable extends ReportTable
{
    protected function heading(): string
    {
        return 'Jualan ikut saluran';
    }

    protected function columns(): array
    {
        return ['channel' => 'Saluran', 'orders' => 'Pesanan', 'revenue_rm' => 'Jualan', 'share_pct' => 'Bahagian'];
    }

    protected function rows(): iterable
    {
        return $this->reports()->channels($this->period())
            ->map(fn ($r) => ['channel' => $r['channel'], 'orders' => $r['orders'], 'revenue_rm' => $r['revenue'], 'share_pct' => $r['share']]);
    }
}
