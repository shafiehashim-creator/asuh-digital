<?php

namespace App\Filament\ReportWidgets;

use App\Filament\ReportWidgets\Concerns\UsesReportPeriod;
use Filament\Widgets\Widget;

/**
 * A small table of figures. Subclasses give the heading, the columns and the rows.
 */
abstract class ReportTable extends Widget
{
    use UsesReportPeriod;

    protected string $view = 'filament.report-widgets.table';

    protected static bool $isLazy = false;

    abstract protected function heading(): string;

    /** @return array<string, string> key => column label (keys ending in "_rm" are shown as ringgit, "_pct" as percent) */
    abstract protected function columns(): array;

    /** @return iterable<array<string, mixed>> */
    abstract protected function rows(): iterable;

    protected function emptyText(): string
    {
        return 'Tiada data untuk tempoh ini.';
    }

    protected function getViewData(): array
    {
        return [
            'heading' => $this->heading(),
            'columns' => $this->columns(),
            'rows' => collect($this->rows())->all(),
            'emptyText' => $this->emptyText(),
        ];
    }
}
