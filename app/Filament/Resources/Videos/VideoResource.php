<?php

namespace App\Filament\Resources\Videos;

use App\Enums\AgeGroup;
use App\Enums\BookStatus;
use App\Enums\VideoProvider;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Models\Video;
use App\Support\VideoId;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Animated videos (Phase 2). Admins and editors manage them like books,
 * through the same syariah review.
 */
class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $modelLabel = 'video';

    protected static ?string $pluralModelLabel = 'Video animasi';

    protected static ?string $slug = 'video';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Video::query()->where('status', BookStatus::Review)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Video menunggu semakan syariah';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Maklumat video')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')->label('Tajuk')->required()->maxLength(200)->columnSpanFull(),
                        Select::make('book_id')->label('Buku berkaitan')
                            ->relationship('book', 'title')->searchable()->preload()
                            ->placeholder('Tiada (video sendiri)')
                            ->helperText('Kosongkan untuk video yang tidak berkaitan dengan buku, contohnya doa harian.'),
                        Select::make('age_group')->label('Umur penonton')->options(AgeGroup::class)->required()->native(false),
                        Select::make('categories')->label('Kategori')
                            ->relationship('categories', 'name', fn ($query) => $query->orderBy('sort_order'))
                            ->multiple()->preload()->native(false)->columnSpanFull(),
                        Textarea::make('synopsis')->label('Sinopsis')->rows(4)->maxLength(2000)->columnSpanFull(),
                    ]),

                Section::make('Gambar kecil')
                    ->columnSpan(1)
                    ->schema([
                        FileUpload::make('thumbnail_path')->label('Gambar kecil (16:9)')
                            ->image()->disk('public')->directory('video-thumbnails')->visibility('public')
                            ->maxSize(10240)
                            ->imageResizeMode('cover')->imageResizeTargetWidth('1280')->imageResizeTargetHeight('720')->imageResizeUpscale(false)
                            ->imageEditor()->imageEditorAspectRatioOptions(['16:9'])
                            ->helperText('JPG, PNG atau WEBP. Disimpan di ASUH sendiri, supaya gambar YouTube tidak mendedahkan pautan video.'),
                    ]),

                Section::make('Fail video')
                    ->description('Fail video disimpan di luar server ASUH. Simpan juga fail asal (contohnya di Google Drive syarikat) supaya mudah berpindah penyedia kemudian.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Select::make('provider')->label('Penyedia')->options(VideoProvider::class)
                            ->default(VideoProvider::YouTube)->required()->native(false)->live(),
                        TextInput::make('provider_video_id')->label('Pautan atau ID video')
                            ->required()->maxLength(200)
                            ->helperText(fn (Get $get) => self::provider($get) === VideoProvider::Bunny
                                ? 'ID video Bunny Stream (contoh: 3f2a…-…).'
                                : 'Tampal pautan YouTube (unlisted, tanda "Made for kids") atau ID 11 aksara.')
                            ->dehydrateStateUsing(fn (?string $state, Get $get) => VideoId::normalise(self::provider($get), (string) $state))
                            ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                $provider = self::provider($get);
                                if (! VideoId::isValid($provider, VideoId::normalise($provider, (string) $value))) {
                                    $fail('Pautan atau ID video tidak sah untuk '.$provider->getLabel().'.');
                                }
                            }),
                        TextInput::make('duration_seconds')->label('Tempoh (minit:saat)')
                            ->placeholder('4:30')->maxLength(6)
                            ->formatStateUsing(fn ($state) => $state ? intdiv((int) $state, 60).':'.str_pad((string) ((int) $state % 60), 2, '0', STR_PAD_LEFT) : null)
                            ->dehydrateStateUsing(fn ($state) => self::seconds($state))
                            ->rule('regex:/^\d{1,3}(:[0-5]\d)?$/'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Tajuk')->searchable()
                    ->description(fn (Video $record) => $record->book?->title ?? 'Video sendiri'),
                TextColumn::make('age_group')->label('Umur')->badge(),
                TextColumn::make('provider')->label('Penyedia')->badge()->color('gray'),
                TextColumn::make('duration_seconds')->label('Tempoh')->state(fn (Video $record) => $record->durationLabel())->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('latestReview.status')->label('Semakan syariah')->badge()->placeholder('Belum dihantar'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(BookStatus::class),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideos::route('/'),
            'create' => CreateVideo::route('/cipta'),
            'edit' => EditVideo::route('/{record}/ubah'),
        ];
    }

    private static function provider(Get $get): VideoProvider
    {
        $value = $get('provider');

        return $value instanceof VideoProvider ? $value : (VideoProvider::tryFrom((string) $value) ?? VideoProvider::YouTube);
    }

    /** "4:30" or "270" (seconds) to seconds. */
    private static function seconds(mixed $state): ?int
    {
        if (blank($state)) {
            return null;
        }

        $parts = explode(':', (string) $state);

        return count($parts) === 2 ? (int) $parts[0] * 60 + (int) $parts[1] : (int) $parts[0] * 60;
    }
}
