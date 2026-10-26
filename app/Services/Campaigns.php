<?php

namespace App\Services;

use App\Enums\CampaignOffer;
use App\Models\Campaign;
use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * The campaigns to show now, and the prices they set. Running campaigns come
 * first (the one ending first wins), then those announced as coming soon
 * (the one starting first).
 */
class Campaigns
{
    public function bar(): ?Campaign
    {
        return $this->shown()->firstWhere('show_bar', true);
    }

    public function banner(): ?Campaign
    {
        return $this->shown()->firstWhere('show_banner', true);
    }

    /**
     * The early bird sells only inside an early bird campaign once one is set
     * up; with none, it sells until its slots are gone (as before campaigns).
     */
    public function earlyBirdWindowOpen(): bool
    {
        // Not memoised: controllers holding this service live across requests in tests.
        $scheduled = Campaign::query()->where('is_active', true)->where('offer', CampaignOffer::EarlyBird)->where('ends_at', '>', now())->exists();

        return ! $scheduled && ! $this->earlyBirdEnded()
            || $this->running()->contains(fn (Campaign $campaign) => $campaign->offer === CampaignOffer::EarlyBird);
    }

    /** The running Langganan Plus promotion, if any. */
    public function plusPromo(): ?Campaign
    {
        return $this->running()->first(fn (Campaign $campaign) => $campaign->offer === CampaignOffer::PlusPromo && $campaign->promo_price_sen > 0);
    }

    /**
     * The campaign price and the normal price it beats, for the countdown
     * (early bird: the pre-order plan against Langganan Tahunan).
     *
     * @return array{0: int, 1: int}|null
     */
    public function prices(Campaign $campaign): ?array
    {
        [$promo, $regular] = match ($campaign->offer) {
            CampaignOffer::EarlyBird => [app(PreorderSlots::class)->plan()?->price_sen, Plan::findByCode(Plan::PAS_TAHUNAN)?->price_sen],
            CampaignOffer::PlusPromo => [$campaign->promo_price_sen, Plan::findByCode(Plan::PAS_PLUS)?->price_sen],
            default => [null, null],
        };

        return $promo && $regular && $promo < $regular ? [$promo, $regular] : null;
    }

    /** An early bird campaign has come and gone: the early bird is over. */
    private function earlyBirdEnded(): bool
    {
        return Campaign::query()->where('is_active', true)->where('offer', CampaignOffer::EarlyBird)->where('ends_at', '<=', now())->exists();
    }

    /** @return Collection<int, Campaign> */
    private function running(): Collection
    {
        return Campaign::query()->running()->orderBy('ends_at')->get();
    }

    /** @return Collection<int, Campaign> */
    private function shown(): Collection
    {
        return $this->running()->concat(Campaign::query()->comingSoon()->orderBy('starts_at')->get());
    }
}
