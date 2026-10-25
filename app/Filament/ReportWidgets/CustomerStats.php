<?php

namespace App\Filament\ReportWidgets;

use App\Filament\ReportWidgets\Concerns\UsesReportPeriod;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStats extends StatsOverviewWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Pelanggan';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $c = $this->reports()->customers($this->period());

        return [
            Stat::make('Akaun ibu bapa baharu', (string) $c['new_accounts'])
                ->description($c['conversion'] === null ? 'Tiada akaun baharu' : $c['conversion'].'% daripadanya sudah membayar'),
            Stat::make('Pelanggan membayar', (string) $c['buyers'])
                ->description("{$c['first_time_buyers']} kali pertama · {$c['repeat_buyers']} pembeli berulang"),
            Stat::make('Langganan aktif sekarang', (string) $c['active_subscriptions'])->description('Keluarga'),
            Stat::make('Keluarga membaca dalam Mod Anak', (string) $c['reading_families'])
                ->description("{$c['children']} profil anak baharu"),
        ];
    }
}
