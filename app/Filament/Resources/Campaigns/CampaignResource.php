<?php

namespace App\Filament\Resources\Campaigns;

use App\Enums\CampaignOffer;
use App\Filament\Resources\Campaigns\Pages\ManageCampaigns;
use App\Models\Campaign;
use App\Models\Plan;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Promotion campaigns with a countdown (user decision 2026-10-26): a bar on
 * every public page and/or a big countdown on the sales pages, until the
 * end date. It can also set the price in its window (the Langganan Tahunan
 * early bird, or a Langganan Plus promotional price) and show as "coming
 * soon" before it starts, while buyers pay the normal price.
 */
class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Jualan';

    protected static ?string $navigationLabel = 'Kempen countdown';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'kempen';

    protected static ?string $pluralModelLabel = 'Kempen countdown';

    protected static ?string $slug = 'kempen';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Kempen')->columnSpanFull()->columns(2)->schema([
                    TextInput::make('title')->label('Tajuk')->required()->maxLength(120)
                        ->helperText('Contoh: Pre-order early bird RM79 · Jualan PBAKL · Pelancaran ASUH Digital'),
                    TextInput::make('promo_code')->label('Kod promosi (pilihan)')->maxLength(30)
                        ->extraInputAttributes(['class' => 'uppercase'])
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? strtoupper(trim($state)) : null)
                        ->helperText('Dipaparkan dengan butang salin. Cipta kodnya sendiri di Kupon promosi.'),
                    Textarea::make('message')->label('Mesej (pilihan)')->rows(2)->maxLength(500)->columnSpanFull()
                        ->helperText('Satu atau dua ayat di kotak countdown besar.'),
                    TextInput::make('cta_label')->label('Teks butang')->maxLength(60)->placeholder('Lihat tawaran'),
                    TextInput::make('cta_url')->label('Pautan butang')->maxLength(255)->placeholder('/langganan')
                        ->regex('#^(/|https://)#')->validationMessages(['regex' => 'Mula dengan / (halaman ASUH) atau https://'])
                        ->helperText('Contoh: /langganan, /langganan-plus, /pre-order atau /buku'),
                ]),
                Section::make('Tawaran dan tempoh')->columnSpanFull()->columns(2)->schema([
                    Select::make('offer')->label('Tawaran')->options(CampaignOffer::class)->default(CampaignOffer::None->value)->required()->live()
                        ->helperText(fn (Get $get) => match (self::offer($get)) {
                            CampaignOffer::EarlyBird => 'Harga early bird (pre-order) hanya dijual antara Mula dan Tamat. Sebelum dan selepas itu, Langganan Tahunan dijual pada harga biasa. Harga dan bilangan slot di Tetapan.',
                            CampaignOffer::PlusPromo => 'Langganan Plus tahunan dijual pada harga promosi antara Mula dan Tamat (langganan baharu dan naik taraf).',
                            default => 'Countdown sahaja. Untuk diskaun dengan kod, cipta kupon dengan tarikh yang sama.',
                        }),
                    TextInput::make('promo_price_sen')->label('Harga promosi Langganan Plus (RM)')
                        ->visible(fn (Get $get) => self::offer($get) === CampaignOffer::PlusPromo)
                        ->required(fn (Get $get) => self::offer($get) === CampaignOffer::PlusPromo)
                        ->numeric()->minValue(1)->step(0.01)
                        ->maxValue(fn () => max(1, (Plan::findByCode(Plan::PAS_PLUS)?->price_sen ?? 100) / 100 - 0.01))
                        ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) round($state * 100) : null)
                        ->helperText(fn () => 'Harga biasa: '.Money::short(Plan::findByCode(Plan::PAS_PLUS)?->price_sen ?? 0).'. Mesti lebih rendah.'),
                    DateTimePicker::make('starts_at')->label('Mula')->seconds(false)->placeholder('Serta-merta')
                        ->required(fn (Get $get) => (bool) $get('show_coming_soon') && self::offer($get) !== CampaignOffer::None)
                        ->helperText('Sebelum tarikh ini, kempen dipaparkan sebagai "Akan datang" (jika dihidupkan di bawah).'),
                    DateTimePicker::make('ends_at')->label('Tamat (countdown ke sini)')->seconds(false)->required()->after('starts_at')
                        ->helperText('Countdown hilang sendiri bila tamat.'),
                ]),
                Section::make('Di mana dipaparkan')->columnSpanFull()->columns(4)->schema([
                    Toggle::make('show_bar')->label('Bar di atas semua halaman')->default(true),
                    Toggle::make('show_banner')->label('Kotak countdown besar')->default(true)
                        ->helperText('Laman utama, Langganan Tahunan dan Langganan Plus.'),
                    Toggle::make('show_coming_soon')->label('Papar "Akan datang" sebelum mula')->default(true)->live()
                        ->helperText('Countdown ke tarikh mula. Pembeli masih boleh membeli pada harga biasa.'),
                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
                Section::make('English (pilihan)')->columnSpanFull()->columns(2)->collapsed()->schema([
                    TextInput::make('title_en')->label('Tajuk (English)')->maxLength(120),
                    TextInput::make('cta_label_en')->label('Teks butang (English)')->maxLength(60),
                    Textarea::make('message_en')->label('Mesej (English)')->rows(2)->maxLength(500)->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ends_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Tajuk')->weight('bold')->searchable()
                    ->description(fn (Campaign $record) => $record->promo_code ? 'Kod '.$record->promo_code : null),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Campaign $record) => $record->status())
                    ->color(fn (string $state) => match ($state) {
                        'Berjalan' => 'success',
                        'Akan datang' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('offer')->label('Tawaran')->badge()->color('warning')
                    ->formatStateUsing(fn (CampaignOffer $state, Campaign $record) => $state === CampaignOffer::PlusPromo && $record->promo_price_sen
                        ? 'Plus '.Money::short($record->promo_price_sen)
                        : ($state === CampaignOffer::EarlyBird ? 'Early bird' : 'Countdown sahaja')),
                TextColumn::make('starts_at')->label('Mula')->dateTime('j M Y, g:i A')->placeholder('Serta-merta'),
                TextColumn::make('ends_at')->label('Tamat')->dateTime('j M Y, g:i A')->sortable(),
                TextColumn::make('places')->label('Dipaparkan')
                    ->state(fn (Campaign $record) => collect([$record->show_bar ? 'Bar' : null, $record->show_banner ? 'Kotak besar' : null])->filter()->implode(' · ') ?: 'Tiada'),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    private static function offer(Get $get): CampaignOffer
    {
        $offer = $get('offer');

        return $offer instanceof CampaignOffer ? $offer : (CampaignOffer::tryFrom((string) $offer) ?? CampaignOffer::None);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCampaigns::route('/'),
        ];
    }
}
