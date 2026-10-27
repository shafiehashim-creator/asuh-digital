<?php

namespace App\Filament\Resources\GameItems\Pages;

use App\Enums\AgeGroup;
use App\Enums\Game;
use App\Filament\Resources\GameItems\GameItemResource;
use App\Models\GameItem;
use App\Support\GameContent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGameItems extends ManageRecords
{
    protected static string $resource = GameItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data) => GameItemResource::reviewed($data)),
        ];
    }

    /** How many active items each game has, and which cannot be played yet. */
    public function getSubheading(): string
    {
        $counts = GameItem::query()->where('is_active', true)->selectRaw('game, count(*) as total')->groupBy('game')->pluck('total', 'game');

        $parts = collect(Game::cases())->filter->editable()->map(function (Game $game) use ($counts) {
            $short = collect([AgeGroup::SixToNine, AgeGroup::TenToThirteen])
                ->reject(fn (AgeGroup $age) => GameContent::playable($game, $age))
                ->map(fn (AgeGroup $age) => $age->getLabel());

            return $game->emoji().' '.$game->label().': '.($counts[$game->value] ?? 0).' aktif'
                .($short->isEmpty() ? '' : ' (belum cukup untuk '.$short->join(', ').')');
        });

        return 'Setiap pusingan memilih secara rawak, jadi lebih banyak item menjadikan permainan lebih segar. Huruf hijaiyah tetap. '.$parts->join(' · ');
    }
}
