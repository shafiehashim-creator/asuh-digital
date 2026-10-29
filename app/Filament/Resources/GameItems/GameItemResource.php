<?php

namespace App\Filament\Resources\GameItems;

use App\Enums\AgeGroup;
use App\Enums\Game;
use App\Filament\Resources\GameItems\Pages\ManageGameItems;
use App\Models\GameItem;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Content for the Mod Anak games (user decision 2026-10-27): staff add,
 * change and switch off items so the games keep growing. Items an editor
 * writes stay off until a Super Admin, after the syariah check, turns them on.
 */
class GameItemResource extends Resource
{
    protected static ?string $model = GameItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Kandungan';

    protected static ?string $navigationLabel = 'Permainan';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'item permainan';

    protected static ?string $pluralModelLabel = 'Permainan';

    protected static ?string $slug = 'permainan';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    /** @return array<string, string> */
    public static function gameOptions(): array
    {
        return collect(Game::cases())->filter->editable()
            ->mapWithKeys(fn (Game $game) => [$game->value => $game->emoji().' '.$game->label()])->all();
    }

    /** What an editor writes or changes waits, switched off, for a Super Admin. */
    public static function reviewed(array $data): array
    {
        if (! (auth()->user()?->isAdmin() ?? false)) {
            $data['is_active'] = false;
        }

        return $data;
    }

    public static function form(Schema $schema): Schema
    {
        $is = fn (Game ...$games) => fn (Get $get) => in_array(Game::tryFrom((string) $get('game')), $games, true);
        // Pilih yang Baik has a kind of item for each level.
        $choice = fn (?string $variant) => fn (Get $get) => Game::tryFrom((string) $get('game')) === Game::GoodChoice && $get('variant') === $variant;

        return $schema->columns(2)->components([
            Select::make('game')->label('Permainan')->options(self::gameOptions())->required()->live(),
            Select::make('age_group')->label('Umur')->options(AgeGroup::class)->default(AgeGroup::Both->value)->required()
                ->helperText('"Kedua-duanya" dimainkan oleh semua anak.'),

            TextInput::make('prompt')->label('Emoji')->visible($is(Game::Memory))->required()->maxLength(16)
                ->helperText('Satu emoji, contohnya 🕌'),
            TextInput::make('answer')->label('Perkataan pada kad')->visible($is(Game::Memory))->required()->maxLength(20),

            Select::make('variant')->label('Jenis soalan')->visible($is(Game::GoodChoice))->live()->columnSpanFull()
                ->placeholder('Baik atau tidak baik (tahap Mudah)')
                ->options(['tindakan' => 'Pilih tindakan terbaik (tahap Sederhana)', 'cerita' => 'Cari ayat bisikan penggoda dalam cerita (tahap Sukar)']),

            Textarea::make('prompt')->label('Situasi')->visible($choice(null))->required()->rows(2)->maxLength(200)->columnSpanFull()
                ->helperText('Contoh: Anas memberi salam ketika masuk ke rumah.'),
            Toggle::make('is_good')->label('Ini perbuatan baik')->visible($choice(null))->inline(false),

            Textarea::make('prompt')->label('Situasi dan soalan')->visible($choice('tindakan'))->required()->rows(2)->maxLength(200)->columnSpanFull()
                ->helperText('Contoh: Anas terjumpa dompet di padang sekolah. Apa patut dia buat?'),
            TextInput::make('answer')->label('Tindakan terbaik')->visible($choice('tindakan'))->required()->maxLength(80),
            TagsInput::make('options')->label('Tindakan lain (kurang baik)')->visible($choice('tindakan'))->required()
                ->rules(['array', 'min:1', 'max:3'])
                ->helperText('1 hingga 3 pilihan. Taip dan tekan Enter.'),

            TextInput::make('prompt')->label('Tajuk cerita')->visible($choice('cerita'))->required()->maxLength(60)
                ->helperText('Contoh: Di kantin'),
            TagsInput::make('options')->label('Ayat cerita, ikut urutan')->visible($choice('cerita'))->required()->reorderable()->live()->columnSpanFull()
                ->rules(['array', 'min:3', 'max:5'])
                ->helperText('3 hingga 5 ayat pendek. Satu daripadanya perbuatan yang salah.'),
            Select::make('answer')->label('Ayat bisikan penggoda (yang salah)')->visible($choice('cerita'))->required()->columnSpanFull()
                ->options(fn (Get $get) => collect($get('options') ?? [])->mapWithKeys(fn ($sentence) => [$sentence => $sentence])->all()),

            Textarea::make('explanation')->label('Penjelasan selepas anak menjawab')->visible($is(Game::GoodChoice))->required()->rows(2)->maxLength(200)->columnSpanFull(),

            TextInput::make('prompt')->label('Tajuk')->visible($is(Game::Order))->required()->maxLength(60)
                ->helperText('Contoh: Rukun Islam'),
            TagsInput::make('options')->label('Senarai ikut tertib yang betul')->visible($is(Game::Order))->required()->reorderable()->columnSpanFull()
                ->rules(['array', 'min:3', 'max:12'])
                ->helperText('3 hingga 12 item. Taip satu item dan tekan Enter; seret untuk menyusun.'),

            TextInput::make('prompt')->label('Perkataan')->visible($is(Game::WordSearch))->required()->maxLength(10)
                ->rules(['regex:/^[A-Za-z]{3,10}$/'])
                ->helperText('Huruf sahaja, 3 hingga 10 huruf, tanpa jarak. Umur 6–9 hanya guna perkataan hingga 8 huruf.'),
            TextInput::make('explanation')->label('Petunjuk (tahap Sukar)')->visible($is(Game::WordSearch))->maxLength(80)
                ->helperText('Di tahap Sukar, anak hanya nampak petunjuk ini, bukan perkataannya. Contoh: Rukun Islam kedua'),

            Textarea::make('prompt')->label('Petunjuk')->visible($is(Game::Prophets))->required()->rows(2)->maxLength(200)->columnSpanFull(),
            TextInput::make('answer')->label('Nama nabi')->visible($is(Game::Prophets))->required()->maxLength(40)
                ->helperText('Tulis sama seperti item lain (contoh: Nabi Nuh), kerana nama ini juga menjadi pilihan jawapan soalan lain.'),

            Textarea::make('prompt')->label('Soalan')->visible($is(Game::QuickQuiz, Game::Science))->required()->rows(2)->maxLength(200)->columnSpanFull()
                ->helperText('Boleh mulakan dengan satu emoji, contohnya 🐟 Ikan bernafas menggunakan …'),
            TextInput::make('answer')->label('Jawapan betul')->visible($is(Game::QuickQuiz, Game::Science))->required()->maxLength(60),
            TagsInput::make('options')->label('Jawapan salah')->visible($is(Game::QuickQuiz, Game::Science))->required()
                ->rules(['array', 'min:1', 'max:3'])
                ->helperText('1 hingga 3 jawapan salah. Taip dan tekan Enter.'),

            TextInput::make('sort_order')->label('Susunan')->integer()->minValue(0)->default(0),
            Toggle::make('is_active')->label('Aktif (dimainkan anak)')->inline(false)
                ->default(fn () => auth()->user()?->isAdmin() ?? false)
                ->disabled(fn () => ! (auth()->user()?->isAdmin() ?? false))
                ->dehydrated(fn () => auth()->user()?->isAdmin() ?? false)
                ->helperText('Hanya Super Admin boleh mengaktifkan, selepas kandungan disemak panel syariah.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('game')->label('Permainan')->formatStateUsing(fn (Game $state) => $state->emoji().' '.$state->label())->sortable(),
                TextColumn::make('prompt')->label('Kandungan')->limit(70)->searchable()->wrap()
                    ->description(fn (GameItem $record) => match ($record->game) {
                        Game::Memory, Game::Prophets => $record->answer,
                        Game::GoodChoice => match ($record->variant) {
                            'tindakan' => 'Tindakan terbaik: '.$record->answer,
                            'cerita' => 'Cerita · penggoda: '.$record->answer,
                            default => $record->is_good ? '👍 Baik' : '👎 Tak baik',
                        },
                        Game::WordSearch => $record->explanation,
                        Game::Order => implode(' → ', $record->options ?? []),
                        Game::QuickQuiz, Game::Science => '✔ '.$record->answer.' · ✘ '.implode(', ', $record->options ?? []),
                        default => null,
                    }),
                TextColumn::make('age_group')->label('Umur'),
                ToggleColumn::make('is_active')->label('Aktif')
                    ->disabled(fn () => ! (auth()->user()?->isAdmin() ?? false)),
            ])
            ->filters([
                SelectFilter::make('game')->label('Permainan')->options(self::gameOptions()),
                SelectFilter::make('age_group')->label('Umur')->options(AgeGroup::class),
                SelectFilter::make('is_active')->label('Status')->options(['1' => 'Aktif', '0' => 'Tidak aktif']),
            ])
            ->recordActions([EditAction::make()->mutateDataUsing(fn (array $data) => self::reviewed($data)), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGameItems::route('/'),
        ];
    }
}
