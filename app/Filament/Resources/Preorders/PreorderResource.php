<?php

namespace App\Filament\Resources\Preorders;

use App\Enums\AgeGroup;
use App\Enums\PreorderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Preorders\Pages\ListPreorders;
use App\Models\Preorder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Read-only list for the demand test. Status changes happen through
 * checkout, payments and the order actions, never by hand.
 */
class PreorderResource extends Resource
{
    protected static ?string $model = Preorder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'pre-order';

    protected static ?string $pluralModelLabel = 'Pre-order';

    protected static ?string $slug = 'pre-order';

    protected static ?int $navigationSort = 20;

    /** Contains buyers' personal data and payments: Super Admin only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()
                    ->description(fn (Preorder $record) => $record->email),
                TextColumn::make('phone')->label('Telefon')->searchable()->copyable(),
                TextColumn::make('state')->label('Negeri')->sortable(),
                TextColumn::make('children_count')->label('Anak')->numeric()->alignEnd(),
                TextColumn::make('children_age_group')->label('Umur anak'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('order.reference')->label('Pesanan')->placeholder('-')
                    ->url(fn (Preorder $record) => $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
                TextColumn::make('created_at')->label('Ditempah')->dateTime('j M Y, g:i A')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(PreorderStatus::class),
                SelectFilter::make('children_age_group')->label('Umur anak')->options(AgeGroup::class),
                SelectFilter::make('state')->label('Negeri')
                    ->options(array_combine(config('asuh.states'), config('asuh.states'))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreorders::route('/'),
        ];
    }
}
