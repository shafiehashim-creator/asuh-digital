<?php

namespace App\Filament\Resources\Books\RelationManagers;

use App\Enums\AudioStatus;
use App\Filament\Resources\Books\Pages\PreparePages;
use App\Models\Book;
use App\Models\BookPage;
use App\Services\Audio\BookAudio;
use App\Services\Audio\ElevenLabs;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Audiobook ("Baca bersama") for one book: a row per page with its text (from
 * the PDF, editable), its voice (uploaded MP3 or AI) and whether the word
 * highlight is ready. The book's audio is shown to readers once published.
 *
 * @property Book $ownerRecord
 */
class PagesRelationManager extends RelationManager
{
    protected static string $relationship = 'pages';

    protected static ?string $title = 'Audio (Baca bersama)';

    protected static ?string $modelLabel = 'halaman';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $aiReady = ElevenLabs::configured();

        return $table
            ->recordTitleAttribute('page')
            ->defaultSort('page')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->poll(fn () => $this->getOwnerRecord()->pages()->where('audio_status', AudioStatus::Queued)->exists() ? '5s' : null)
            ->description(fn () => $this->summary())
            ->columns([
                TextColumn::make('page')->label('Hal.')->sortable(),
                TextColumn::make('text')->label('Teks yang dibaca')->limit(90)->wrap()->placeholder('Tiada teks (halaman dilangkau)'),
                TextColumn::make('audio_status')->label('Audio')->badge()
                    ->description(fn (BookPage $record) => $record->audio_error ?: match ($record->audio_source) {
                        'ai' => 'Suara AI',
                        'upload' => 'Rakaman dimuat naik',
                        default => null,
                    }),
                IconColumn::make('timings')->label('Serlahan')->boolean()
                    ->state(fn (BookPage $record) => $record->hasAudio() ? filled($record->timings) : null)
                    ->tooltip(fn (BookPage $record) => match (true) {
                        ! $record->hasAudio() => null,
                        filled($record->timings) && filled($record->word_boxes) => 'Perkataan diserlahkan di atas halaman',
                        filled($record->timings) => 'Perkataan diserlahkan di bawah halaman (PDF tiada teks)',
                        default => 'Belum ada masa perkataan',
                    }),
                TextColumn::make('audio_seconds')->label('Tempoh')->placeholder('-')
                    ->formatStateUsing(fn ($state) => $state ? gmdate('i:s', (int) round($state)) : null),
            ])
            ->headerActions([
                Action::make('setUp')->label('Sediakan senarai halaman')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->visible(fn () => $this->getOwnerRecord()->pages()->doesntExist())
                    ->action(function (BookAudio $audio) {
                        $count = $audio->ensurePages($this->getOwnerRecord());
                        $count
                            ? Notification::make()->success()->title("{$count} halaman disediakan")
                                ->body('Teks halaman diambil dari PDF semasa "Versi pantas" dijalankan. Jalankan semula Versi pantas jika teks kosong.')->send()
                            : Notification::make()->warning()->title('Muat naik PDF buku dahulu')->send();
                    }),
                Action::make('extractText')->label('Ambil teks dari PDF')
                    ->icon(Heroicon::OutlinedDocumentText)->color('gray')
                    ->url(fn () => PreparePages::getUrl(['record' => $this->getOwnerRecord()]))
                    ->visible(fn () => $this->getOwnerRecord()->files()->exists())
                    ->tooltip('Jalankan Versi pantas: gambar halaman dan teks setiap halaman diambil dari PDF.'),
                Action::make('uploadMany')->label('Muat naik audio')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->modalHeading('Muat naik fail suara')
                    ->modalDescription('Satu fail MP3 setiap halaman. Nombor halaman diambil daripada nama fail, contohnya halaman-03.mp3 untuk halaman 3.')
                    ->schema([
                        FileUpload::make('files')->label('Fail MP3')->multiple()->storeFiles(false)
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])->maxSize(20480)->maxFiles(200)->required(),
                    ])
                    ->action(fn (array $data, BookAudio $audio) => $this->uploadMany($data['files'], $audio)),
                Action::make('generateAll')->label('Jana suara AI')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->visible($aiReady)
                    ->requiresConfirmation()
                    ->modalHeading('Jana suara AI untuk halaman tanpa audio?')
                    ->modalDescription(fn () => $this->generationEstimate())
                    ->modalSubmitActionLabel('Jana')
                    ->action(function (BookAudio $audio) {
                        $pages = $this->getOwnerRecord()->pages()->where('audio_status', AudioStatus::None)->get();
                        $count = $audio->queueGeneration($pages);
                        Notification::make()->success()->title($count ? "{$count} halaman sedang dijana" : 'Tiada halaman untuk dijana')
                            ->body($count ? 'Biasanya siap dalam 1–3 minit. Senarai ini dikemas kini sendiri.' : 'Setiap halaman tanpa audio perlu ada teks dahulu.')->send();
                    }),
                Action::make('publish')
                    ->label(fn () => $this->getOwnerRecord()->audio_published_at ? 'Sembunyikan audio' : 'Terbitkan audio')
                    ->icon(fn () => $this->getOwnerRecord()->audio_published_at ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedSpeakerWave)
                    ->color(fn () => $this->getOwnerRecord()->audio_published_at ? 'gray' : 'success')
                    ->visible(fn () => auth()->user()?->isAdmin() && $this->getOwnerRecord()->pages()->where('audio_status', AudioStatus::Ready)->exists())
                    ->requiresConfirmation()
                    ->modalDescription(fn () => $this->getOwnerRecord()->audio_published_at
                        ? 'Butang 🎧 Dengar akan hilang dari pembaca buku ini.'
                        : 'Pastikan suara, terutama sebutan doa, ayat dan nama, sudah disemak. Pembaca nampak butang 🎧 Dengar bila suis "Buka audiobook" di Tetapan dihidupkan.')
                    ->action(function () {
                        $book = $this->getOwnerRecord();
                        $book->forceFill(['audio_published_at' => $book->audio_published_at ? null : now()])->save();
                        Notification::make()->success()->title($book->audio_published_at ? 'Audio diterbitkan' : 'Audio disembunyikan')->send();
                    }),
            ])
            ->recordActions([
                Action::make('listen')->label('Dengar')->icon(Heroicon::OutlinedPlay)->color('gray')
                    ->visible(fn (BookPage $record) => $record->hasAudio())
                    ->url(fn (BookPage $record) => URL::temporarySignedRoute('admin.audio.page', now()->addHour(), ['page' => $record]), shouldOpenInNewTab: true),
                Action::make('editText')->label('Teks')->icon(Heroicon::OutlinedPencilSquare)->color('gray')
                    ->fillForm(fn (BookPage $record) => ['text' => $record->text])
                    ->schema([
                        Textarea::make('text')->label('Teks yang dibaca untuk halaman ini')->rows(6)->maxLength(5000)
                            ->helperText('Betulkan teks jika perlu (contohnya susunan belon komik). Kosongkan untuk halaman tanpa bacaan. Selepas mengubah teks, jana atau muat naik semula suara supaya serlahan tepat.'),
                    ])
                    ->action(fn (BookPage $record, array $data) => $record->update(['text' => $data['text'] ?: null])),
                Action::make('upload')->label('Muat naik')->icon(Heroicon::OutlinedArrowUpTray)->color('gray')
                    ->schema([
                        FileUpload::make('file')->label('Fail MP3 untuk halaman ini')->storeFiles(false)
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3'])->maxSize(20480)->required(),
                    ])
                    ->action(function (BookPage $record, array $data, BookAudio $audio) {
                        $audio->storeUpload($record, $this->uploadedFile($data['file']));
                        Notification::make()->success()->title("Audio halaman {$record->page} disimpan")->send();
                    }),
                Action::make('generate')->label('Jana AI')->icon(Heroicon::OutlinedSparkles)->color('gray')
                    ->visible(fn (BookPage $record) => $aiReady && $record->hasText() && $record->audio_status !== AudioStatus::Queued)
                    ->requiresConfirmation(fn (BookPage $record) => $record->hasAudio())
                    ->modalDescription('Suara sedia ada untuk halaman ini akan diganti.')
                    ->action(fn (BookPage $record, BookAudio $audio) => $audio->queueGeneration([$record])),
                Action::make('remove')->label('Padam audio')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->visible(fn (BookPage $record) => $record->hasAudio())
                    ->requiresConfirmation()
                    ->action(fn (BookPage $record, BookAudio $audio) => $audio->removeAudio($record)),
            ])
            ->toolbarActions([
                BulkAction::make('generateSelected')->label('Jana suara AI')->icon(Heroicon::OutlinedSparkles)
                    ->visible($aiReady)
                    ->requiresConfirmation()
                    ->modalDescription('Suara sedia ada untuk halaman yang dipilih akan diganti. Halaman tanpa teks dilangkau.')
                    ->action(function (Collection $records, BookAudio $audio) {
                        $count = $audio->queueGeneration($records);
                        Notification::make()->success()->title("{$count} halaman sedang dijana")->send();
                    }),
            ])
            ->emptyStateHeading('Belum ada halaman audio')
            ->emptyStateDescription('Tekan "Sediakan senarai halaman". Teks setiap halaman diambil dari PDF semasa Versi pantas.');
    }

    private function summary(): string
    {
        $pages = $this->getOwnerRecord()->pages()->get();
        if ($pages->isEmpty()) {
            return 'Suara untuk setiap halaman: rakaman MP3 atau suara AI. Anak tekan 🎧 Dengar dalam pembaca, perkataan diserlahkan semasa dibaca dan halaman diselak sendiri.';
        }

        $ready = $pages->filter->hasAudio()->count();
        $withText = $pages->filter->hasText()->count();
        $published = $this->getOwnerRecord()->audio_published_at ? 'Diterbitkan' : 'Belum diterbitkan';

        return "{$ready} / {$pages->count()} halaman ada audio · {$withText} halaman ada teks · {$published}"
            .(ElevenLabs::configured() ? '' : ' · Suara AI belum disambung (ELEVENLABS_API_KEY dalam .env)');
    }

    private function generationEstimate(): string
    {
        $chars = $this->getOwnerRecord()->pages()->where('audio_status', AudioStatus::None)->get()
            ->filter->hasText()->sum(fn (BookPage $page) => mb_strlen((string) $page->text));
        $minutes = max(1, (int) round($chars / 900));

        return "Kira-kira {$chars} aksara (lebih kurang {$minutes} minit suara). Kos ElevenLabs dikira ikut bilangan aksara. Halaman yang sudah ada audio tidak disentuh.";
    }

    /** @param  array<int|string, TemporaryUploadedFile|UploadedFile|string>  $files */
    private function uploadMany(array $files, BookAudio $audio): void
    {
        $book = $this->getOwnerRecord();
        $audio->ensurePages($book);
        $saved = [];
        $skipped = [];

        foreach ($files as $file) {
            $file = $this->uploadedFile($file);
            $name = $file->getClientOriginalName();
            $number = BookAudio::pageFromFileName($name);
            $page = $number ? $book->pages()->where('page', $number)->first() : null;
            if (! $page) {
                $skipped[] = $name;

                continue;
            }
            $audio->storeUpload($page, $file);
            $saved[] = $number;
        }

        sort($saved);
        Notification::make()->status($saved ? 'success' : 'warning')
            ->title(count($saved).' fail disimpan')
            ->body(implode(' ', array_filter([
                $saved ? 'Halaman '.implode(', ', $saved).'.' : null,
                $skipped ? 'Tidak dipadankan (tiada nombor halaman yang sah): '.implode(', ', $skipped).'.' : null,
            ])))
            ->persistent()->send();
    }

    private function uploadedFile(mixed $file): UploadedFile
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }

        // A stored path (tests or a pre-stored upload).
        $path = Storage::disk('local')->path((string) $file);

        return new UploadedFile($path, basename($path), 'audio/mpeg', null, true);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Book && $ownerRecord->files()->exists();
    }
}
