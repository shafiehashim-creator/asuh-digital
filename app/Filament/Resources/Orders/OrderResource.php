<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Orders are financial records: read-only here. They change only through
 * PaymentProcessor (CHIP callback) or the confirm/reject actions on ViewOrder.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|UnitEnum|null $navigationGroup = 'Jualan';

    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'pesanan';

    protected static ?string $pluralModelLabel = 'Pesanan';

    protected static ?string $slug = 'pesanan';

    /** Editors have no access to money (CLAUDE.md). */
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::awaitingConfirmationQuery(Order::query())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pindahan bank menunggu pengesahan';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pesanan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference')->label('Rujukan')->copyable(),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('item')->label('Item')->state(fn (Order $record) => $record->itemName()),
                        TextEntry::make('amount_sen')->label('Harga')->formatStateUsing(fn (int $state) => Money::format($state)),
                        TextEntry::make('processing_fee_sen')->label('Caj pemprosesan (dibayar pembeli)')->formatStateUsing(fn (int $state) => Money::format($state)),
                        TextEntry::make('total')->label('Jumlah dibayar')->state(fn (Order $record) => Money::format($record->totalSen())),
                        TextEntry::make('payment_method')->label('Kaedah')
                            ->formatStateUsing(fn (Order $record) => $record->payment_method->getLabel().($record->chip_method ? ' · '.$record->chip_method->label() : '')),
                        TextEntry::make('chip_purchase_id')->label('ID purchase CHIP')->placeholder('-')->copyable(),
                        TextEntry::make('created_at')->label('Dicipta')->dateTime('j M Y, g:i A'),
                        TextEntry::make('paid_at')->label('Dibayar')->dateTime('j M Y, g:i A')->placeholder('-'),
                        TextEntry::make('confirmedBy.name')->label('Disahkan oleh')->placeholder('-'),
                        TextEntry::make('confirmed_at')->label('Disahkan pada')->dateTime('j M Y, g:i A')->placeholder('-'),
                        TextEntry::make('admin_note')->label('Catatan admin')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Pembeli')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.name')->label('Nama'),
                        TextEntry::make('user.email')->label('E-mel')->copyable(),
                        TextEntry::make('user.phone')->label('Telefon')->placeholder('-')->copyable(),
                        TextEntry::make('preorder.state')->label('Negeri')->placeholder('-'),
                        TextEntry::make('preorder.children_count')->label('Bilangan anak')->placeholder('-'),
                        TextEntry::make('preorder.children_age_group')->label('Umur anak')->placeholder('-'),
                    ]),
                Section::make('Slot pre-order')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('preorder.status')->label('Status slot')->badge()->placeholder('-'),
                        TextEntry::make('preorder.reserved_until')->label('Ditempah sehingga')->dateTime('j M Y, g:i A')->placeholder('-'),
                        TextEntry::make('late_payment')
                            ->label('Bayaran lewat')
                            ->state(fn (Order $record) => static::isLatePayment($record) ? 'Ya: dibayar selepas tempahan tamat' : 'Tidak')
                            ->color(fn (Order $record) => static::isLatePayment($record) ? 'warning' : null),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label('Rujukan')->searchable()->copyable(),
                TextColumn::make('user.name')->label('Pembeli')->searchable()
                    ->description(fn (Order $record) => $record->user?->email),
                TextColumn::make('amount_sen')->label('Jumlah')->formatStateUsing(fn (int $state) => Money::format($state))->alignEnd()
                    ->description(fn (Order $record) => $record->processing_fee_sen > 0 ? '+ caj '.Money::format($record->processing_fee_sen) : null),
                TextColumn::make('payment_method')->label('Kaedah')->badge()->color('gray'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('preorder.status')->label('Slot')->badge()->placeholder('-'),
                TextColumn::make('created_at')->label('Dicipta')->dateTime('j M Y, g:i A')->sortable(),
                TextColumn::make('paid_at')->label('Dibayar')->dateTime('j M Y, g:i A')->sortable()->placeholder('-')->toggleable(),
            ])
            ->filters([
                Filter::make('awaiting_confirmation')
                    ->label('Pindahan bank menunggu pengesahan')
                    ->query(fn (Builder $query) => static::awaitingConfirmationQuery($query)),
                SelectFilter::make('status')->label('Status')->options(OrderStatus::class),
                SelectFilter::make('payment_method')->label('Kaedah')->options(PaymentMethod::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function awaitingConfirmationQuery(Builder $query): Builder
    {
        return $query
            ->where('payment_method', PaymentMethod::BankTransfer)
            ->where('status', OrderStatus::Pending)
            // Same rule as Order::isAwaitingConfirmation(): covers pre-orders,
            // regular passes and single books alike.
            ->whereNotNull('bank_receipt_path');
    }

    public static function isLatePayment(Order $order): bool
    {
        $reservedUntil = $order->preorder?->reserved_until;

        return $order->paid_at && $reservedUntil && $order->paid_at->greaterThan($reservedUntil);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
