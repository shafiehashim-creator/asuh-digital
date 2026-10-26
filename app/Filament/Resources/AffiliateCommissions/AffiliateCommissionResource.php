<?php

namespace App\Filament\Resources\AffiliateCommissions;

use App\Enums\CommissionStatus;
use App\Filament\Resources\AffiliateCommissions\Pages\ListAffiliateCommissions;
use App\Models\AffiliateCommission;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Every commission, for checking and for cancelling one after a refund. */
class AffiliateCommissionResource extends Resource
{
    protected static ?string $model = AffiliateCommission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Jualan';

    protected static ?string $navigationLabel = 'Komisen affiliate';

    protected static ?int $navigationSort = 16;

    protected static ?string $modelLabel = 'komisen';

    protected static ?string $pluralModelLabel = 'Komisen affiliate';

    protected static ?string $slug = 'komisen-affiliate';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['affiliate.user', 'order.plan', 'order.book']))
            ->columns([
                TextColumn::make('created_at')->label('Tarikh')->date('j M Y'),
                TextColumn::make('affiliate.user.name')->label('Affiliate')->searchable()
                    ->description(fn (AffiliateCommission $record) => $record->affiliate?->code),
                TextColumn::make('order.reference')->label('Pesanan')->searchable()
                    ->description(fn (AffiliateCommission $record) => $record->order?->itemName()),
                TextColumn::make('order.amount_sen')->label('Harga')->formatStateUsing(fn (int $state) => Money::format($state)),
                TextColumn::make('amount_sen')->label('Komisen')->weight('bold')
                    ->formatStateUsing(fn (int $state, AffiliateCommission $record) => Money::format($state).' ('.rtrim(rtrim(number_format($record->percent, 2), '0'), '.').'%)'),
                TextColumn::make('status')->label('Status')->badge()
                    ->description(fn (AffiliateCommission $record) => match (true) {
                        $record->isDue() => 'Boleh dibayar',
                        $record->status === CommissionStatus::Pending => 'Mulai '.$record->payable_at->translatedFormat('j M Y'),
                        $record->paid_at !== null => 'Dibayar '.$record->paid_at->translatedFormat('j M Y').($record->payment_reference ? ' · '.$record->payment_reference : ''),
                        default => null,
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(CommissionStatus::class),
                Filter::make('due')->label('Boleh dibayar sekarang')->query(fn (Builder $query) => $query->due()),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Batalkan')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (AffiliateCommission $record) => $record->status === CommissionStatus::Pending)
                    ->requiresConfirmation()
                    ->modalDescription('Contohnya selepas bayaran balik kepada pembeli. Komisen ini tidak akan dibayar.')
                    ->action(fn (AffiliateCommission $record) => $record->update(['status' => CommissionStatus::Cancelled])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateCommissions::route('/'),
        ];
    }
}
