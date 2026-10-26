<?php

namespace App\Filament\Resources\Books;

use App\Enums\AgeGroup;
use App\Enums\BookEdition;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Filament\Resources\Books\Pages\CreateBook;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Filament\Resources\Books\Pages\PreparePages;
use App\Filament\Resources\Books\RelationManagers\ContributorsRelationManager;
use App\Filament\Resources\Books\RelationManagers\ReviewsRelationManager;
use App\Models\Book;
use App\Models\BookFile;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Books are managed by admins and editors (CLAUDE.md). The status is never
 * edited directly: EditBook's actions move a book through
 * draft -> review -> published, and Book itself refuses to publish without
 * an approved syariah review.
 */
class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|UnitEnum|null $navigationGroup = 'Kandungan';

    protected static ?string $navigationLabel = 'Buku';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'buku';

    protected static ?string $pluralModelLabel = 'Buku';

    protected static ?string $slug = 'buku';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Book::query()->where('status', BookStatus::Review)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Buku menunggu semakan syariah';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Maklumat buku')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')->label('Tajuk')->required()->maxLength(200)->columnSpanFull(),
                        TextInput::make('author')->label('Penulis')->required()->maxLength(150),
                        Select::make('categories')->label('Kategori')
                            ->relationship('categories', 'name', fn ($query) => $query->orderBy('sort_order'))
                            ->multiple()->preload()->required()->native(false)
                            ->helperText('Boleh pilih lebih daripada satu.'),
                        Select::make('age_group')->label('Umur pembaca')->options(AgeGroup::class)->required()->native(false),
                        TextInput::make('single_price_sen')->label('Harga single (RM)')
                            ->helperText('Kosongkan jika buku hanya melalui Langganan Tahunan.')
                            ->numeric()->minValue(0)->step(0.01)->prefix('RM')
                            ->formatStateUsing(fn ($state) => filled($state) ? number_format($state / 100, 2, '.', '') : null)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) round(((float) $state) * 100) : null),
                        // Contracts are financial: Super Admin only.
                        TextInput::make('royalty_single_percent')->label('Kadar royalti khas jualan single (%)')
                            ->helperText(fn () => 'Kosongkan untuk guna kadar lalai ('.rtrim(rtrim(number_format((float) Setting::get('royalty_single_percent'), 2), '0'), '.').'%) di Tetapan → Royalti.')
                            ->numeric()->minValue(0)->maxValue(100)->step(0.5)->suffix('%')
                            ->visible(fn () => auth()->user()?->isAdmin() ?? false),
                        Textarea::make('synopsis')->label('Sinopsis')->required()->rows(5)->maxLength(3000)->columnSpanFull(),
                    ]),

                Section::make('Kaver')
                    ->columnSpan(1)
                    ->schema([
                        FileUpload::make('cover_path')->label('Gambar kaver')
                            ->image()->disk('public')->directory('covers')->visibility('public')
                            // Any ratio is accepted; large images are shrunk in the browser before upload.
                            ->maxSize(20480)
                            ->imageResizeMode('contain')->imageResizeTargetWidth('1200')->imageResizeTargetHeight('1600')->imageResizeUpscale(false)
                            ->imageEditor()->imageEditorAspectRatioOptions([null, '3:4'])
                            ->helperText('JPG, PNG atau WEBP, maksimum 20 MB. Apa-apa saiz boleh; gambar besar dikecilkan secara automatik. Nisbah 3:4 (potret) paling cantik. Klik ikon pensel untuk memotong gambar jika perlu.'),
                    ]),

                Section::make('Fail e-book (PDF)')
                    ->description('Disimpan di kawasan peribadi server dan hanya dibuka melalui pembaca, selepas semakan akses.')
                    ->columnSpanFull()
                    ->schema([
                        Toggle::make('is_enhanced_edition')->label('Paparkan edisi dipertingkat kepada pembaca (jika ada)'),
                        Repeater::make('files')->label('Fail')
                            ->relationship()
                            ->schema([
                                Select::make('edition')->label('Edisi')->options(BookEdition::class)->required()->native(false)
                                    ->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                FileUpload::make('path')->label('Fail PDF')
                                    ->disk(BookFile::DISK)->directory('books')->visibility('private')
                                    ->acceptedFileTypes(['application/pdf'])->maxSize(204800)
                                    ->helperText('Maksimum 200 MB. Fail besar mengambil masa beberapa minit untuk dimuat naik; jangan tutup halaman.')
                                    ->required()->downloadable(false)->openable(false)->previewable(false),
                                TextInput::make('page_count')->label('Bilangan halaman')->integer()->minValue(1)->maxValue(2000),
                            ])
                            ->columns(3)
                            ->maxItems(2)
                            ->addActionLabel('Tambah fail')
                            ->defaultItems(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('cover_path')->label('Kaver')->disk('public')->imageHeight(56),
                TextColumn::make('title')->label('Tajuk')->searchable()->description(fn (Book $record) => $record->author),
                TextColumn::make('categories.name')->label('Kategori')->badge(),
                TextColumn::make('age_group')->label('Umur'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('latestReview.status')->label('Semakan syariah')->badge()->placeholder('Belum dihantar'),
                TextColumn::make('published_at')->label('Diterbitkan')->date('j M Y')->placeholder('-')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(BookStatus::class),
                SelectFilter::make('categories')->label('Kategori')->relationship('categories', 'name')->multiple(),
                SelectFilter::make('age_group')->label('Umur')->options(AgeGroup::class),
                SelectFilter::make('review')->label('Semakan syariah')->options(ReviewStatus::class)
                    ->query(fn ($query, array $data) => filled($data['value'])
                        ? $query->whereHas('latestReview', fn ($q) => $q->where('status', $data['value']))
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ReviewsRelationManager::class,
            ContributorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBooks::route('/'),
            'create' => CreateBook::route('/create'),
            'edit' => EditBook::route('/{record}/edit'),
            'pages' => PreparePages::route('/{record}/versi-pantas'),
        ];
    }
}
