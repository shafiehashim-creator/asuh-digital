<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\Role;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    /**
     * A new staff member gets a temporary password from the Super Admin or
     * an e-mail link to set one, then logs in at /admin. An existing account
     * with the same e-mail (a customer, or staff whose access was removed)
     * is given the role instead of a second account.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStaff')
                ->label('Tambah staf')
                ->icon(Heroicon::OutlinedUserPlus)
                ->modalDescription('Staf log masuk di /admin. Jika e-mel ini sudah ada akaun (pelanggan, atau staf yang aksesnya pernah dibuang), akaun itu diberi akses semula.')
                ->schema([
                    TextInput::make('name')->label('Nama')->required()->maxLength(255),
                    TextInput::make('email')->label('E-mel')->email()->required()->maxLength(255),
                    Select::make('role')->label('Peranan')->options(StaffResource::roleOptions())->default(Role::Editor->value)->required(),
                    Radio::make('access')->label('Cara log masuk kali pertama')
                        ->options([
                            'password' => 'Saya tetapkan kata laluan sementara (paling mudah)',
                            'email' => 'Hantar e-mel pautan untuk menetapkan kata laluan (sah 24 jam)',
                            'keep' => 'Akaun sedia ada: guna kata laluan mereka sendiri',
                        ])
                        ->default('password')->required()->live(),
                    ...array_slice(StaffResource::accessFields(), 1),
                ])
                ->modalSubmitActionLabel('Tambah staf')
                ->action(function (array $data) {
                    $email = Str::lower($data['email']);
                    $user = User::query()->where('email', $email)->first();

                    if ($user?->role?->isInstitutionStaff()) {
                        Notification::make()->title('E-mel ini milik guru atau pentadbir institusi. Guna e-mel lain untuk staf ASUH.')->danger()->send();

                        return;
                    }

                    if ($user?->isStaff()) {
                        Notification::make()->title('E-mel ini sudah menjadi staf.')->warning()->send();

                        return;
                    }

                    if ($user === null && $data['access'] === 'keep') {
                        Notification::make()->title('E-mel ini belum ada akaun. Pilih kata laluan sementara atau e-mel pautan.')->danger()->send();

                        return;
                    }

                    $user ??= User::query()->create(['name' => $data['name'], 'email' => $email, 'password' => Str::password(32)]);
                    $user->forceFill(['role' => $data['role'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();

                    if ($data['access'] === 'keep') {
                        Notification::make()->title('Akaun sedia ada diberi akses staf.')->body('Mereka log masuk di /admin dengan kata laluan sendiri.')->success()->send();

                        return;
                    }

                    StaffResource::grantAccess($user, $data);
                }),
        ];
    }
}
