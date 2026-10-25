<?php

namespace App\Filament\ReportWidgets;

use App\Filament\ReportWidgets\Concerns\UsesReportPeriod;
use App\Services\Reports\AdminReports;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TrafficStats extends StatsOverviewWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Trafik laman';

    protected ?string $description = 'Lawatan awam sahaja (staf tidak dikira). Direkod sejak 26 Okt 2026, tanpa alamat IP.';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $period = $this->period();
        $now = $this->reports()->traffic($period);
        $before = $this->reports()->traffic($period->previous());
        $change = AdminReports::change($now['visitors'], $before['visitors']);

        return [
            Stat::make('Pelawat unik', number_format($now['visitors']))
                ->description($change === null ? 'Tiada perbandingan' : ($change >= 0 ? '+' : '').$change.'% berbanding tempoh sebelum')
                ->color($change === null ? 'gray' : ($change >= 0 ? 'success' : 'danger')),
            Stat::make('Paparan halaman', number_format($now['views'])),
            Stat::make('Pendaftaran akaun', number_format($now['signups'])),
            Stat::make('Kadar penukaran', $now['conversion'] === null ? '-' : $now['conversion'].'%')
                ->description('Pesanan dibayar bagi setiap pelawat'),
        ];
    }
}
