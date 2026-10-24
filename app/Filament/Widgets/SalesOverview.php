<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Preorders\PreorderResource;
use App\Services\DashboardStats;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Ujian Permintaan dan jualan';

    /** Financial figures: Super Admin only. */
    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $stats = app(DashboardStats::class);
        $preorders = $stats->preorders();
        $sales = $stats->sales();

        $percent = $preorders['limit'] > 0 ? (int) round($preorders['paid'] / $preorders['limit'] * 100) : 0;

        return [
            Stat::make('Pre-order dibayar', "{$preorders['paid']} / {$preorders['limit']}")
                ->description("{$percent}% daripada target · {$preorders['awaiting']} menunggu pengesahan · {$preorders['reserved']} sedang bayar")
                ->descriptionIcon(Heroicon::OutlinedTicket)
                ->color($percent >= 100 ? 'success' : 'primary')
                ->url(PreorderResource::getUrl()),
            Stat::make('Baki slot', (string) $preorders['remaining'])
                ->description($preorders['remaining'] === 0 ? 'Slot early bird telah habis' : 'Termasuk slot yang dilepaskan semula')
                ->color($preorders['remaining'] === 0 ? 'danger' : 'gray'),
            Stat::make('Jualan hari ini', Money::format($sales['today']))
                ->description('Minggu ini '.Money::format($sales['week']).' · Bulan ini '.Money::format($sales['month']))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(OrderResource::getUrl()),
        ];
    }
}
