<?php

namespace App\Services;

use App\Models\Campaign;
use Illuminate\Support\Collection;

/**
 * The campaign to show now: of those running, the one ending first, so the
 * most urgent countdown wins when two overlap.
 */
class Campaigns
{
    public function bar(): ?Campaign
    {
        return $this->current()->firstWhere('show_bar', true);
    }

    public function banner(): ?Campaign
    {
        return $this->current()->firstWhere('show_banner', true);
    }

    /** @return Collection<int, Campaign> */
    private function current()
    {
        return once(fn () => Campaign::query()->running()->orderBy('ends_at')->get());
    }
}
