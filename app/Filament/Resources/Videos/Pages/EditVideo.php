<?php

namespace App\Filament\Resources\Videos\Pages;

use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\BookNotApproved;
use App\Filament\Resources\Videos\VideoResource;
use App\Models\Video;
use App\Services\ContentWorkflow;
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
use InvalidArgumentException;

/**
 * Same steps as a book: Hantar -> Semak -> Terbitkan.
 *
 * @property Video $record
 */
class EditVideo extends EditRecord
{
    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Tonton')
                ->icon(Heroicon::OutlinedPlay)
                ->color('gray')
                ->url(fn () => route('admin.video.preview', $this->record), shouldOpenInNewTab: true),

            Action::make('submitForReview')
                ->label('Hantar untuk semakan syariah')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalDescription('Penyemak akan menonton video ini sebelum ia boleh diterbitkan.')
                ->visible(fn () => $this->record->status === BookStatus::Draft)
                ->action(fn (ContentWorkflow $workflow) => $this->run(fn () => $workflow->submitForReview($this->record), 'Video dihantar untuk semakan syariah.')),

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
                        ->helperText('Wajib jika perlu pembetulan. Nyatakan minit dan saat yang terlibat.'),
                ])
                ->modalSubmitActionLabel('Simpan keputusan')
                ->action(fn (array $data, ContentWorkflow $workflow) => $this->run(
                    fn () => $workflow->review($this->record, auth()->user(), $data['decision'] === 'approve', $data['notes'] ?? null),
                    $data['decision'] === 'approve' ? 'Semakan syariah lulus. Video boleh diterbitkan.' : 'Video dikembalikan untuk pembetulan.',
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
                ->action(fn (array $data, ContentWorkflow $workflow) => $this->run(
                    fn () => $workflow->publish($this->record, Carbon::parse($data['published_at'])),
                    'Video diterbitkan.',
                )),

            Action::make('unpublish')
                ->label('Nyahterbit')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === BookStatus::Published)
                ->action(fn (ContentWorkflow $workflow) => $this->run(fn () => $workflow->unpublish($this->record), 'Video dinyahterbit.')),

            DeleteAction::make()->visible(fn () => $this->record->status !== BookStatus::Published),
        ];
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
