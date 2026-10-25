<?php

namespace App\Filament\Resources\Books\Pages;

use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\BookNotApproved;
use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use App\Services\BookWorkflow;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

/**
 * @property Book $record
 */
class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Pratonton')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn () => route('reader.show', $this->record), shouldOpenInNewTab: true)
                ->visible(fn () => Route::has('reader.show') && $this->record->files()->exists()),

            Action::make('pages')
                ->label(fn () => $this->needsPageImages() ? 'Buat versi pantas' : 'Versi pantas')
                ->icon(Heroicon::OutlinedBolt)
                ->color(fn () => $this->needsPageImages() ? 'warning' : 'gray')
                ->url(fn () => PreparePages::getUrl(['record' => $this->record]))
                ->visible(fn () => $this->record->files()->exists()),

            Action::make('submitForReview')
                ->label('Hantar untuk semakan syariah')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalDescription('Penyemak akan menyemak kandungan buku ini sebelum ia boleh diterbitkan.')
                ->visible(fn () => $this->record->status === BookStatus::Draft)
                ->action(fn (BookWorkflow $workflow) => $this->run(fn () => $workflow->submitForReview($this->record), 'Buku dihantar untuk semakan syariah.')),

            Action::make('review')
                ->label('Semak syariah')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('warning')
                ->visible(fn () => $this->record->status === BookStatus::Review)
                ->schema([
                    Radio::make('decision')->label('Keputusan')->required()->live()
                        ->options(['approve' => 'Lulus', 'reject' => 'Perlu pembetulan']),
                    Textarea::make('notes')->label('Catatan')->rows(4)->maxLength(2000)
                        ->required(fn (Get $get) => $get('decision') === 'reject')
                        ->helperText('Wajib jika perlu pembetulan. Catatan ini disimpan dalam sejarah semakan.'),
                ])
                ->modalSubmitActionLabel('Simpan keputusan')
                ->action(fn (array $data, BookWorkflow $workflow) => $this->run(
                    fn () => $workflow->review($this->record, auth()->user(), $data['decision'] === 'approve', $data['notes'] ?? null),
                    $data['decision'] === 'approve' ? 'Semakan syariah lulus. Buku boleh diterbitkan.' : 'Buku dikembalikan untuk pembetulan.',
                )),

            Action::make('publish')
                ->label('Terbitkan')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('success')
                ->visible(fn () => $this->record->status !== BookStatus::Published
                    && $this->record->latestReview?->status === ReviewStatus::Approved)
                ->schema([
                    DateTimePicker::make('published_at')->label('Tarikh terbit')->default(now())->required()->native(false)
                        ->helperText('Pilih tarikh akan datang untuk menjadualkan penerbitan.'),
                ])
                ->modalSubmitActionLabel('Terbitkan')
                ->action(fn (array $data, BookWorkflow $workflow) => $this->run(
                    fn () => $workflow->publish($this->record, Carbon::parse($data['published_at'])),
                    'Buku diterbitkan.',
                )),

            Action::make('unpublish')
                ->label('Nyahterbit')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Buku akan disembunyikan daripada katalog. Semakan syariah yang lulus dikekalkan.')
                ->visible(fn () => $this->record->status === BookStatus::Published)
                ->action(fn (BookWorkflow $workflow) => $this->run(fn () => $workflow->unpublish($this->record), 'Buku dinyahterbit.')),

            DeleteAction::make()
                ->visible(fn () => $this->record->status !== BookStatus::Published),
        ];
    }

    /** A new or replaced PDF goes on to "versi pantas" so phones open it quickly. */
    protected function afterSave(): void
    {
        if ($this->needsPageImages()) {
            $this->redirect(PreparePages::getUrl(['record' => $this->record, 'auto' => 1]));
        }
    }

    private function needsPageImages(): bool
    {
        return $this->record->files()->whereNull('pages_ready_at')->exists();
    }

    private function run(callable $step, string $success): void
    {
        try {
            $step();
        } catch (InvalidArgumentException|BookNotApproved $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->record->refresh();
        $this->refreshFormData(['status']);

        Notification::make()->title($success)->success()->send();
    }
}
