<?php

namespace App\Filament\Resources\Institutions\Pages;

use App\Enums\Role;
use App\Filament\Resources\Institutions\InstitutionResource;
use App\Models\Institution;
use App\Models\User;
use App\Services\Invitations;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

/**
 * @property Institution $record
 */
class EditInstitution extends EditRecord
{
    protected static string $resource = InstitutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('inviteAdmin')
                ->label('Jemput pentadbir institusi')
                ->icon(Heroicon::OutlinedEnvelope)
                ->modalDescription('Pentadbir institusi (contohnya guru besar) boleh menjemput guru lain dan mencipta kelas. Jemputan sah selama 7 hari.')
                ->schema([
                    TextInput::make('name')->label('Nama')->required()->maxLength(255),
                    TextInput::make('email')->label('E-mel')->email()->required()
                        ->rule(Rule::unique(User::class, 'email'))
                        ->validationMessages(['unique' => 'E-mel ini sudah mempunyai akaun ASUH Digital.']),
                ])
                ->modalSubmitActionLabel('Hantar jemputan')
                ->action(function (array $data, Invitations $invitations) {
                    $invitations->invite($this->record, $data['email'], $data['name'], Role::InstitutionAdmin, auth()->user());

                    Notification::make()->title('Jemputan dihantar ke '.$data['email'].'.')->success()->send();
                }),
        ];
    }
}
