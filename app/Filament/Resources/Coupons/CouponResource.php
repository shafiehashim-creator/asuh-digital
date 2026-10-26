<?php

namespace App\Filament\Resources\Coupons;

use App\Enums\CouponType;
use App\Enums\OrderStatus;
use App\Enums\PromoProduct;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Models\Affiliate;
use App\Models\Coupon;
use App\Models\Institution;
use App\Support\Money;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Promotion codes (user decision 2026-10-26). Buyers type them in the one
 * "Kod promosi" box at checkout; uses count paid orders only.
 */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Jualan';

    protected static ?string $navigationLabel = 'Kupon promosi';

    protected static ?int $navigationSort = 14;

    protected static ?string $modelLabel = 'kupon';

    protected static ?string $pluralModelLabel = 'Kupon promosi';

    protected static ?string $slug = 'kupon';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('code')->label('Kod')->required()->maxLength(30)
                    ->regex('/^[A-Za-z0-9-]+$/')->validationMessages(['regex' => 'Huruf, nombor dan sengkang sahaja.'])
                    ->unique(ignoreRecord: true)
                    // One box takes every kind of code, so they must not clash.
                    ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                        if (Institution::findByParentCode($value) || Affiliate::query()->where('code', strtoupper((string) $value))->exists()) {
                            $fail('Kod ini sudah digunakan sebagai kod sekolah atau kod affiliate.');
                        }
                    })
                    ->extraInputAttributes(['class' => 'uppercase'])
                    ->helperText('Contoh: RAYA20. Pembeli menaip kod ini di ruang "Kod promosi".'),
                TextInput::make('description')->label('Catatan (untuk admin)')->maxLength(255),
                Select::make('type')->label('Jenis diskaun')->options(CouponType::class)->default(CouponType::Percent->value)->required()->live(),
                TextInput::make('value')->label(fn (Get $get) => self::isFixed($get) ? 'Diskaun (RM)' : 'Diskaun (%)')
                    ->numeric()->required()->minValue(fn (Get $get) => self::isFixed($get) ? 0.01 : 1)
                    ->maxValue(fn (Get $get) => self::isFixed($get) ? 10000 : 100)
                    ->step(fn (Get $get) => self::isFixed($get) ? 0.01 : 1)
                    // Amounts are kept in sen like every price.
                    ->formatStateUsing(fn ($state, ?Coupon $record) => $record?->type === CouponType::Fixed ? $state / 100 : $state)
                    ->dehydrateStateUsing(fn ($state, Get $get) => self::isFixed($get) ? (int) round($state * 100) : (int) $state)
                    ->helperText('Harga selepas diskaun tidak akan kurang daripada RM1.00.'),
                Select::make('applies_to')->label('Untuk produk')->options(PromoProduct::couponOptions())->default(PromoProduct::All->value)->required()
                    ->helperText('Naik taraf ke Langganan Plus dan pre-order early bird tidak menerima kupon.'),
                TextInput::make('max_uses')->label('Had penggunaan')->integer()->minValue(1)->placeholder('Tiada had')
                    ->helperText('Jumlah pesanan berbayar yang boleh guna kod ini.'),
                DateTimePicker::make('starts_at')->label('Mula')->seconds(false)->placeholder('Serta-merta'),
                DateTimePicker::make('ends_at')->label('Tamat')->seconds(false)->placeholder('Tiada tarikh tamat')->after('starts_at'),
                Toggle::make('once_per_customer')->label('Sekali sahaja setiap pelanggan')->default(true),
                Toggle::make('is_active')->label('Aktif')->default(true),
            ]);
    }

    private static function isFixed(Get $get): bool
    {
        $type = $get('type');

        return ($type instanceof CouponType ? $type : CouponType::tryFrom((string) $type)) === CouponType::Fixed;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Kod')->searchable()->copyable()->weight('bold')
                    ->description(fn (Coupon $record) => $record->description),
                TextColumn::make('value')->label('Diskaun')->state(fn (Coupon $record) => $record->describeValue()),
                TextColumn::make('applies_to')->label('Produk')->badge()->color('gray'),
                TextColumn::make('uses')->label('Digunakan')
                    ->state(fn (Coupon $record) => $record->usesCount().($record->max_uses ? ' / '.$record->max_uses : '')),
                TextColumn::make('sales')->label('Jualan')
                    ->state(fn (Coupon $record) => Money::format((int) $record->orders()->where('status', OrderStatus::Paid)->sum('amount_sen'))),
                TextColumn::make('ends_at')->label('Tamat')->dateTime('j M Y, g:i A')->placeholder('Tiada'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                // Codes already on orders stay, so receipts and reports keep them.
                DeleteAction::make()->visible(fn (Coupon $record) => $record->orders()->doesntExist()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCoupons::route('/'),
        ];
    }
}
