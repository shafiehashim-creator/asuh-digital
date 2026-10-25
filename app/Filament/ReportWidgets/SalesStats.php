<?php

namespace App\Filament\ReportWidgets;

use App\Filament\ReportWidgets\Concerns\UsesReportPeriod;
use App\Services\Reports\AdminReports;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStats extends StatsOverviewWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Jualan';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $period = $this->period();
        $now = $this->reports()->sales($period);
        $before = $this->reports()->sales($period->previous());

        return [
            $this->compare('Jumlah jualan', Money::format($now['revenue']), $now['revenue'], $before['revenue'], Heroicon::OutlinedBanknotes),
            $this->compare('Pesanan dibayar', (string) $now['orders'], $now['orders'], $before['orders'], Heroicon::OutlinedShoppingBag),
            $this->compare('Purata nilai pesanan', Money::format($now['average']), $now['average'], $before['average'], Heroicon::OutlinedCalculator),
            Stat::make('Diskaun kod sekolah', Money::format($now['discounts']))->description('Diberi dalam tempoh ini')->color('gray'),
        ];
    }

    private function compare(string $label, string $value, int $now, int $before, $icon): Stat
    {
        $change = AdminReports::change($now, $before);

        return Stat::make($label, $value)
            ->description($change === null ? 'Tiada perbandingan' : ($change >= 0 ? '+' : '').$change.'% berbanding tempoh sebelum')
            ->descriptionIcon($change === null ? $icon : ($change >= 0 ? Heroicon::OutlinedArrowTrendingUp : Heroicon::OutlinedArrowTrendingDown))
            ->color($change === null ? 'gray' : ($change >= 0 ? 'success' : 'danger'));
    }
}
