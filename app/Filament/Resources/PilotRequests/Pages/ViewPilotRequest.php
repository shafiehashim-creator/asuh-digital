<?php

namespace App\Filament\Resources\PilotRequests\Pages;

use App\Enums\LicenseKind;
use App\Enums\PilotStatus;
use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\PilotRequests\PilotRequestResource;
use App\Models\Institution;
use App\Models\PilotRequest;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * @property PilotRequest $record
 */
class ViewPilotRequest extends ViewRecord
{
    protected static string $resource = PilotRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createInstitution')
                ->label('Mulakan pilot')
                ->icon(Heroicon::OutlinedAcademicCap)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn () => 'Institusi akan dicipta dengan lesen pilot percuma selama '.(int) Setting::get('pilot_duration_days')
                    .' hari untuk '.$this->record->student_count.' murid. Selepas itu, jemput pentadbir institusi.')
                ->visible(fn () => ! $this->record->institution()->exists())
                ->action(function () {
                    $institution = DB::transaction(function () {
                        $institution = Institution::query()->create([
                            'name' => $this->record->institution_name,
                            'type' => $this->record->institution_type,
                            'state' => $this->record->state,
                            'district' => $this->record->district,
                            'pilot_request_id' => $this->record->id,
                        ]);
                        $institution->licenses()->create([
                            'kind' => LicenseKind::Pilot,
                            'max_students' => max(1, (int) $this->record->student_count),
                            'starts_on' => today(),
                            'ends_on' => today()->addDays((int) Setting::get('pilot_duration_days') - 1),
                        ]);
                        $this->record->update(['status' => PilotStatus::PilotActive]);

                        return $institution;
                    });

                    $this->redirect(InstitutionResource::getUrl('edit', ['record' => $institution]));
                }),

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
