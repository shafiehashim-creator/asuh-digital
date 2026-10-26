<?php

namespace App\Filament\Resources\Videos;

use App\Enums\AgeGroup;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Enums\VideoProvider;
use App\Exceptions\ContentNotApproved;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use App\Filament\Resources\Videos\Pages\EditVideo;
use App\Filament\Resources\Videos\Pages\ListVideos;
use App\Models\Category;
use App\Models\Video;
use App\Services\ContentWorkflow;
use App\Support\VideoId;
use BackedEnum;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use UnitEnum;

/**
 * Animated videos (Phase 2). Admins and editors manage them like books,
 * through the same syariah review.
 */
class VideoResource extends Resource
{
    protected static ?string $model = Video::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFilm;

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|UnitEnum|null $navigationGroup = 'Kandungan';

    protected static ?string $navigationLabel = 'Video animasi';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'video';

    protected static ?string $pluralModelLabel = 'Video animasi';

    protected static ?string $slug = 'video';

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
                        Toggle::make('is_free_sample')->label('Percuma untuk ditonton')
                            ->helperText('Sesiapa boleh menonton video ini sepenuhnya di halaman Video animasi dan halaman buku, untuk menarik ibu bapa melanggan Langganan Plus. Pilih 1–3 video terbaik; video lain kekal dikunci.')
                            ->columnSpanFull(),
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
                // Switch straight from the list, no need to open the video.
                ToggleColumn::make('is_free_sample')->label('Percuma')
                    ->tooltip('Percuma untuk ditonton di halaman awam'),
                TextColumn::make('latestReview.status')->label('Semakan syariah')->badge()->placeholder('Belum dihantar'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(BookStatus::class),
            ])
            ->recordActions([EditAction::make()])
            // Tick several videos, then act on all of them. Each video goes
            // through the same rules as its own page; any that do not fit
            // (for example, publishing without a passed review) are skipped.
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulk('submit', 'Hantar untuk semakan syariah', Heroicon::OutlinedPaperAirplane, 'gray',
                        fn (Video $video) => $video->status === BookStatus::Draft,
                        fn (Video $video, ContentWorkflow $workflow) => $workflow->submitForReview($video),
                        'bukan draf'),
                    self::bulk('approve', 'Lulus semakan syariah', Heroicon::OutlinedShieldCheck, 'warning',
                        fn (Video $video) => $video->status === BookStatus::Review,
                        fn (Video $video, ContentWorkflow $workflow) => $workflow->review($video, auth()->user(), true),
                        'belum dihantar untuk semakan')
                        ->modalDescription('Sahkan bahawa anda sudah menonton setiap video yang dipilih. Video yang ditolak perlu disemak satu-satu dengan catatan.'),
                    self::bulk('publish', 'Terbitkan', Heroicon::OutlinedGlobeAlt, 'success',
                        fn (Video $video) => $video->status !== BookStatus::Published && $video->latestReview?->status === ReviewStatus::Approved,
                        fn (Video $video, ContentWorkflow $workflow, array $data) => $workflow->publish($video, Carbon::parse($data['published_at'])),
                        'semakan syariah belum lulus atau sudah diterbitkan')
                        ->schema([
                            DateTimePicker::make('published_at')->label('Tarikh terbit')->default(now())->required()->native(false)
                                ->helperText('Pilih tarikh akan datang untuk menjadualkan penerbitan.'),
                        ]),
                    self::bulk('unpublish', 'Nyahterbit', Heroicon::OutlinedEyeSlash, 'gray',
                        fn (Video $video) => $video->status === BookStatus::Published,
                        fn (Video $video, ContentWorkflow $workflow) => $workflow->unpublish($video),
                        'belum diterbitkan'),
                    self::bulk('makeFree', 'Tandakan percuma', Heroicon::OutlinedGift, 'gray',
                        fn (Video $video) => ! $video->is_free_sample,
                        fn (Video $video) => $video->update(['is_free_sample' => true]),
                        'sudah percuma'),
                    self::bulk('makePaid', 'Buang tanda percuma', Heroicon::OutlinedLockClosed, 'gray',
                        fn (Video $video) => $video->is_free_sample,
                        fn (Video $video) => $video->update(['is_free_sample' => false]),
                        'bukan percuma'),
                    self::bulk('categories', 'Tambah kategori', Heroicon::OutlinedTag, 'gray',
                        fn () => true,
                        fn (Video $video, ContentWorkflow $workflow, array $data) => $video->categories()->syncWithoutDetaching($data['categories']),
                        '')
                        ->schema([
                            Select::make('categories')->label('Kategori')->multiple()->required()->native(false)
                                ->options(fn () => Category::query()->orderBy('sort_order')->pluck('name', 'id')),
                        ]),
                    self::bulk('age', 'Tukar umur penonton', Heroicon::OutlinedUsers, 'gray',
                        fn () => true,
                        fn (Video $video, ContentWorkflow $workflow, array $data) => $video->update(['age_group' => $data['age_group']]),
                        '')
                        ->schema([
                            Select::make('age_group')->label('Umur penonton')->options(AgeGroup::class)->required()->native(false),
                        ]),
                    DeleteBulkAction::make()->label('Padam')
                        ->modalDescription('Video yang sudah diterbitkan tidak dipadam. Nyahterbit dahulu.')
                        ->using(fn (Collection $records) => $records->reject(fn (Video $video) => $video->status === BookStatus::Published)->each->delete()),
                ])->label('Tindakan pukal'),
            ]);
    }

    /**
     * A bulk action that runs $step on each selected video that $fits, then
     * reports how many were done and how many were skipped and why.
     */
    private static function bulk(string $name, string $label, Heroicon $icon, string $color, callable $fits, callable $step, string $skipReason): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->modalHeading($label)
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, ContentWorkflow $workflow) use ($label, $fits, $step, $skipReason) {
                $done = 0;
                $skipped = [];

                foreach ($records as $video) {
                    if (! $fits($video)) {
                        $skipped[] = $video->title.($skipReason ? " ({$skipReason})" : '');

                        continue;
                    }

                    try {
                        $step($video, $workflow, $data);
                        $done++;
                    } catch (InvalidArgumentException|ContentNotApproved $e) {
                        $skipped[] = $video->title.' ('.$e->getMessage().')';
                    }
                }

                Notification::make()
                    ->title($label.': '.$done.' video')
                    ->body($skipped ? 'Dilangkau: '.implode('; ', $skipped) : null)
                    ->color($skipped ? 'warning' : 'success')
                    ->icon($skipped ? Heroicon::OutlinedExclamationTriangle : Heroicon::OutlinedCheckCircle)
                    ->send();
            });
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
