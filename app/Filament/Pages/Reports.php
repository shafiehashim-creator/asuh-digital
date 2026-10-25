<?php

namespace App\Filament\Pages;

use App\Filament\ReportWidgets;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Admin reports: sales, products, sales channels, customers and traffic over
 * a chosen period. Financial, so Super Admin only.
 */
class Reports extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'laporan';

    protected static ?string $title = 'Laporan';

    protected static ?string $navigationLabel = 'Laporan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = -1;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    DatePicker::make('startDate')->label('Dari')->default(now()->subDays(29)->toDateString())
                        ->native(false)->displayFormat('j M Y')->maxDate(now()),
                    DatePicker::make('endDate')->label('Hingga')->default(now()->toDateString())
                        ->native(false)->displayFormat('j M Y')->maxDate(now()),
                ]),
        ]);
    }

    public function getColumns(): int|array
    {
        return ['md' => 2, 'xl' => 2];
    }

    public function getWidgets(): array
    {
        return [
            // Jualan
            ReportWidgets\SalesStats::class,
            ReportWidgets\SalesChart::class,
            // Produk
            ReportWidgets\ProductsTable::class,
            ReportWidgets\MostReadTable::class,
            // Saluran jualan
            ReportWidgets\ChannelsChart::class,
            ReportWidgets\ChannelsTable::class,
            ReportWidgets\CampaignsTable::class,
            ReportWidgets\PaymentMethodsTable::class,
            ReportWidgets\SchoolsTable::class,
            // Pelanggan
            ReportWidgets\CustomerStats::class,
            ReportWidgets\CustomersChart::class,
            ReportWidgets\StatesTable::class,
            // Trafik
            ReportWidgets\TrafficStats::class,
            ReportWidgets\TrafficChart::class,
            ReportWidgets\TrafficSourcesTable::class,
            ReportWidgets\DevicesChart::class,
            ReportWidgets\TopPagesTable::class,
            ReportWidgets\FunnelTable::class,
        ];
    }
}
