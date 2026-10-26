<?php

namespace App\Filament\Resources\Campaigns;

use App\Filament\Resources\Campaigns\Pages\ManageCampaigns;
use App\Models\Campaign;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Promotion campaigns with a countdown (user decision 2026-10-26): a bar on
 * every public page and/or a big countdown on the sales pages, until the
 * end date. The countdown only shows the deadline; the offer behind it (a
 * coupon, the early bird) keeps its own dates.
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
                Section::make('Tempoh')->columnSpanFull()->columns(2)->schema([
                    DateTimePicker::make('starts_at')->label('Mula dipaparkan')->seconds(false)->placeholder('Serta-merta'),
                    DateTimePicker::make('ends_at')->label('Tamat (countdown ke sini)')->seconds(false)->required()->after('starts_at')
                        ->helperText('Selaraskan dengan tarikh tamat kupon atau tawaran. Countdown hilang sendiri bila tamat.'),
                ]),
                Section::make('Di mana dipaparkan')->columnSpanFull()->columns(3)->schema([
                    Toggle::make('show_bar')->label('Bar di atas semua halaman')->default(true),
                    Toggle::make('show_banner')->label('Kotak countdown besar')->default(true)
                        ->helperText('Laman utama, Langganan Tahunan dan Langganan Plus.'),
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

    public static function getPages(): array
    {
        return [
            'index' => ManageCampaigns::route('/'),
        ];
    }
}
