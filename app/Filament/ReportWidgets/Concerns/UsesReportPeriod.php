<?php

namespace App\Filament\ReportWidgets\Concerns;

use App\Services\Reports\AdminReports;
use App\Services\Reports\Period;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Report widgets read the period from the Laporan page filters. */
trait UsesReportPeriod
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function period(): Period
    {
        return Period::fromFilters($this->pageFilters);
    }

    protected function reports(): AdminReports
    {
        return app(AdminReports::class);
    }
}
