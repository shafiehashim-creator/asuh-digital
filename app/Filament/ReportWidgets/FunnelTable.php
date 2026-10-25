<?php

namespace App\Filament\ReportWidgets;

class FunnelTable extends ReportTable
{
    protected int|string|array $columnSpan = 'full';

    protected function heading(): string
    {
        return 'Corong penukaran';
    }

    protected function columns(): array
    {
        return ['step' => 'Langkah', 'count' => 'Bilangan', 'of_first_pct' => 'Daripada pelawat', 'of_previous_pct' => 'Daripada langkah sebelum'];
    }

    protected function rows(): iterable
    {
        $steps = $this->reports()->funnel($this->period());
        $first = $steps[0]['count'];

        return collect($steps)->map(fn ($s, $i) => [
            'step' => $s['step'],
            'count' => $s['count'],
            'of_first_pct' => $first ? $s['count'] / $first * 100 : null,
            'of_previous_pct' => $i === 0 ? null : ($steps[$i - 1]['count'] ? $s['count'] / $steps[$i - 1]['count'] * 100 : null),
        ]);
    }
}
