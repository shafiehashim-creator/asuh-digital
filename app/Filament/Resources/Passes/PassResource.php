<?php

namespace App\Filament\Resources\Passes;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Passes\Pages\ListPasses;
use App\Models\Pass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only list of yearly passes. Passes are made only by
 * PaymentProcessor::markPaid(), never by hand (CLAUDE.md).
 */
class PassResource extends Resource
{
    protected static ?string $model = Pass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $modelLabel = 'pas';

    protected static ?string $pluralModelLabel = 'Pas Tahunan';

    protected static ?string $slug = 'pas';

    protected static ?int $navigationSort = 12;

    /** Tied to payments: Super Admin only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'plan', 'order']))
            ->columns([
                TextColumn::make('user.name')->label('Pemilik')->searchable()
                    ->description(fn (Pass $record) => $record->user?->email)
                    ->url(fn (Pass $record) => $record->user ? CustomerResource::getUrl('view', ['record' => $record->user]) : null),
                TextColumn::make('plan.name')->label('Pelan'),
                TextColumn::make('state')->label('Status')->badge()
                    ->state(fn (Pass $record) => static::stateLabel($record))
                    ->color(fn (string $state) => match ($state) {
                        'Aktif' => 'success',
                        'Tamat tidak lama lagi' => 'warning',
                        'Menunggu tarikh launch', 'Belum bermula' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('starts_at')->label('Mula')->date('j M Y')->sortable()->placeholder('Tarikh launch'),
                TextColumn::make('ends_at')->label('Tamat')->date('j M Y')->sortable()->placeholder('-'),
                TextColumn::make('order.reference')->label('Pesanan')->placeholder('-')
                    ->url(fn (Pass $record) => $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'expiring' => 'Tamat dalam 30 hari',
                        'waiting' => 'Menunggu tarikh launch',
                        'ended' => 'Sudah tamat',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'active' => $query->active(),
                        'expiring' => $query->active()->where('ends_at', '<=', now()->addDays(30)),
                        'waiting' => $query->whereNull('starts_at'),
                        'ended' => $query->where('ends_at', '<=', now()),
                        default => $query,
                    }),
            ]);
    }

    public static function stateLabel(Pass $pass): string
    {
        return match (true) {
            $pass->starts_at === null => 'Menunggu tarikh launch',
            $pass->starts_at->isFuture() => 'Belum bermula',
            (bool) $pass->ends_at?->isPast() => 'Tamat',
            (bool) $pass->ends_at?->lessThanOrEqualTo(now()->addDays(30)) => 'Tamat tidak lama lagi',
            default => 'Aktif',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPasses::route('/'),
        ];
    }
}
