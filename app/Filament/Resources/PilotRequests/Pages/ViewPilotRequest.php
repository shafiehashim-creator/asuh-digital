<?php

namespace App\Filament\Resources\PilotRequests\Pages;

use App\Enums\PilotStatus;
use App\Filament\Resources\PilotRequests\PilotRequestResource;
use App\Models\PilotRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * @property PilotRequest $record
 */
class ViewPilotRequest extends ViewRecord
{
    protected static string $resource = PilotRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('changeStatus')
                ->label('Tukar status')
                ->icon(Heroicon::OutlinedArrowPath)
                ->fillForm(fn () => ['status' => $this->record->status])
                ->schema([
                    Select::make('status')
                        ->label('Status')
                        ->options(PilotStatus::class)
                        ->required()
                        ->native(false),
                ])
                ->modalSubmitActionLabel('Simpan')
                ->action(function (array $data) {
                    $this->record->update(['status' => $data['status']]);

                    Notification::make()->title('Status dikemas kini.')->success()->send();
                }),
        ];
    }
}
