<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\PilotRequests\PilotRequestResource;
use App\Services\DashboardStats;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PassesAndPilotsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Langganan dan institusi';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $stats = app(DashboardStats::class);
        $passes = $stats->passes();
        $pilots = $stats->pilots();
        $licenses = $stats->licenses();

        return [
            Stat::make('Langganan Tahunan aktif', (string) $passes['active'])
                ->description("{$passes['expiring']} tamat dalam 30 hari")
                ->descriptionIcon(Heroicon::OutlinedIdentification)
                ->color($passes['expiring'] > 0 ? 'warning' : 'primary'),
            Stat::make('Langganan menunggu tarikh launch', (string) $passes['waiting'])
                ->description($passes['waiting'] ? 'Tetapkan tarikh launch di halaman Tetapan' : 'Tiada')
                ->color($passes['waiting'] ? 'warning' : 'gray'),
            Stat::make('Pilot: membayar / permohonan', "{$pilots['paying']} / {$pilots['total']}")
                ->description("{$pilots['active']} pilot sedang aktif")
                ->descriptionIcon(Heroicon::OutlinedBuildingLibrary)
                ->color('primary')
                ->url(PilotRequestResource::getUrl()),
            Stat::make('Institusi berlesen', (string) $licenses['active'])
                ->description("{$licenses['paid']} berbayar · {$licenses['expiring']} tamat dalam 30 hari")
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color($licenses['expiring'] > 0 ? 'warning' : 'primary')
                ->url(InstitutionResource::getUrl()),
        ];
    }
}
