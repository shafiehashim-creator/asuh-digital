<?php

namespace App\Filament\Resources\Customers;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Passes\PassResource;
use App\Models\Pass;
use App\Models\User;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Parent accounts, read-only. Account details are the parent's own to change
 * (Akaun saya); passes and books come only from paid orders.
 */
class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'pelanggan';

    protected static ?string $pluralModelLabel = 'Pelanggan';

    protected static ?string $slug = 'pelanggan';

    protected static ?int $navigationSort = 11;

    /** Buyers' personal data: Super Admin only. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', Role::Parent);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akaun')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nama'),
                        TextEntry::make('email')->label('E-mel')->copyable(),
                        TextEntry::make('phone')->label('Telefon')->placeholder('-')->copyable(),
                        TextEntry::make('email_verified_at')->label('E-mel disahkan')->dateTime('j M Y, g:i A')->placeholder('Belum'),
                        TextEntry::make('created_at')->label('Mendaftar')->dateTime('j M Y, g:i A'),
                        TextEntry::make('paid_total')->label('Jumlah dibayar')
                            ->state(fn (User $record) => Money::format((int) $record->orders()->where('status', OrderStatus::Paid)->sum('amount_sen'))),
                    ]),
                Section::make('Langganan Tahunan')
                    ->schema([
                        RepeatableEntry::make('passes')
                            ->hiddenLabel()
                            ->placeholder('Tiada Langganan Tahunan.')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('plan.name')->label('Pelan'),
                                TextEntry::make('state')->label('Status')->badge()
                                    ->state(fn (Pass $record) => PassResource::stateLabel($record)),
                                TextEntry::make('starts_at')->label('Mula')->date('j M Y')->placeholder('Tarikh launch'),
                                TextEntry::make('ends_at')->label('Tamat')->date('j M Y')->placeholder('-'),
                            ]),
                    ]),
                Section::make('Profil anak')
                    ->schema([
                        RepeatableEntry::make('children')
                            ->hiddenLabel()
                            ->placeholder('Tiada profil anak.')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('nickname')->label('Nama panggilan'),
                                TextEntry::make('age_group')->label('Umur')->badge(),
                            ]),
                    ]),
                Section::make('Buku dibeli')
                    ->schema([
                        RepeatableEntry::make('entitlements')
                            ->hiddenLabel()
                            ->placeholder('Tiada buku single dibeli.')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('book.title')->label('Buku'),
                                TextEntry::make('created_at')->label('Dibeli')->date('j M Y'),
                            ]),
                    ]),
                Section::make('Pesanan')
                    ->schema([
                        RepeatableEntry::make('orders')
                            ->hiddenLabel()
                            ->placeholder('Tiada pesanan.')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('reference')->label('Rujukan')->copyable(),
                                TextEntry::make('item')->label('Item')->state(fn ($record) => $record->itemName()),
                                TextEntry::make('amount_sen')->label('Jumlah')->formatStateUsing(fn (int $state) => Money::format($state)),
                                TextEntry::make('status')->label('Status')->badge(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['orders as paid_orders_count' => fn (Builder $q) => $q->where('status', OrderStatus::Paid)])
                ->withExists(['passes as has_active_pass' => fn (Builder $q) => $q->active()]))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(['name', 'email'])
                    ->description(fn (User $record) => $record->email),
                TextColumn::make('phone')->label('Telefon')->searchable()->copyable()->placeholder('-'),
                IconColumn::make('has_active_pass')->label('Langganan aktif')->boolean(),
                TextColumn::make('paid_orders_count')->label('Pesanan dibayar')->numeric()->alignEnd(),
                IconColumn::make('email_verified_at')->label('E-mel disahkan')->boolean()
                    ->state(fn (User $record) => $record->email_verified_at !== null)->toggleable(),
                TextColumn::make('created_at')->label('Mendaftar')->dateTime('j M Y')->sortable(),
            ])
            ->filters([
                Filter::make('active_pass')->label('Ada langganan aktif')
                    ->query(fn (Builder $query) => $query->whereHas('passes', fn (Builder $q) => $q->active())),
                Filter::make('no_purchase')->label('Belum membeli')
                    ->query(fn (Builder $query) => $query->whereDoesntHave('orders', fn (Builder $q) => $q->where('status', OrderStatus::Paid))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
