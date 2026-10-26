<?php

namespace App\Filament\Resources\PlusWaitlist;

use App\Filament\Resources\PlusWaitlist\Pages\ListPlusWaitlist;
use App\Models\PlusWaitlistEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only list of "Beritahu saya" sign-ups for Langganan Plus. They are
 * e-mailed once when "Jual Langganan Plus" is switched on in Tetapan.
 */
class PlusWaitlistResource extends Resource
{
    protected static ?string $model = PlusWaitlistEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = 'Jualan';

    protected static ?string $navigationLabel = 'Senarai menunggu Plus';

    protected static ?int $navigationSort = 14;

    protected static ?string $modelLabel = 'pendaftar';

    protected static ?string $pluralModelLabel = 'Senarai menunggu Plus';

    protected static ?string $slug = 'senarai-menunggu-plus';

    /** E-mail addresses are personal data: Super Admin only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = PlusWaitlistEntry::whereNull('notified_at')->count();

        return $waiting ? (string) $waiting : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->label('E-mel')->searchable()->copyable(),
                TextColumn::make('user.name')->label('Akaun')->placeholder('Tiada akaun'),
                TextColumn::make('locale')->label('Bahasa')->formatStateUsing(fn (string $state) => strtoupper($state === 'ms' ? 'BM' : $state)),
                TextColumn::make('created_at')->label('Mendaftar')->dateTime('j M Y, g:i A')->sortable(),
                TextColumn::make('notified_at')->label('Dimaklumkan')->dateTime('j M Y, g:i A')->placeholder('Belum'),
            ])
            ->filters([
                TernaryFilter::make('notified_at')->label('Dimaklumkan')->nullable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlusWaitlist::route('/'),
        ];
    }
}
