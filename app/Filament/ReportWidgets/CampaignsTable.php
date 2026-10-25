<?php

namespace App\Filament\ReportWidgets;

class CampaignsTable extends ReportTable
{
    protected function heading(): string
    {
        return 'Kempen dan sumber (UTM)';
    }

    protected function columns(): array
    {
        return ['source' => 'Sumber / medium', 'campaign' => 'Kempen', 'orders' => 'Pesanan', 'revenue_rm' => 'Jualan'];
    }

    protected function rows(): iterable
    {
        return $this->reports()->campaigns($this->period())
            ->map(fn ($r) => ['source' => $r['source'], 'campaign' => $r['campaign'], 'orders' => $r['orders'], 'revenue_rm' => $r['revenue']]);
    }

    protected function emptyText(): string
    {
        return 'Belum ada jualan daripada pautan berUTM atau laman lain. Tambah ?utm_source=facebook&utm_medium=cpc&utm_campaign=nama-kempen pada pautan iklan.';
    }
}
