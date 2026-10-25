<?php

namespace App\Services\Reports;

use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

/** The date range a report covers, and the same length just before it for comparison. */
final class Period
{
    public function __construct(public readonly Carbon $from, public readonly Carbon $to) {}

    /** From the report page filters; the last 30 days by default. */
    public static function fromFilters(?array $filters): self
    {
        $to = filled($filters['endDate'] ?? null) ? Carbon::parse($filters['endDate'])->endOfDay() : now()->endOfDay();
        $from = filled($filters['startDate'] ?? null) ? Carbon::parse($filters['startDate'])->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        return $from->greaterThan($to) ? new self($to->copy()->startOfDay(), $from->copy()->endOfDay()) : new self($from, $to);
    }

    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->copy()->subDays($days), $this->from->copy()->subSecond());
    }

    public function days(): int
    {
        return (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
    }

    /** @return list<string> Y-m-d for every day in the range */
    public function dates(): array
    {
        return array_map(fn ($d) => $d->toDateString(), iterator_to_array(CarbonPeriod::create($this->from->copy()->startOfDay(), $this->to->copy()->startOfDay())));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function range(): array
    {
        return [$this->from, $this->to];
    }
}
