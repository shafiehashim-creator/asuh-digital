<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Enums\Role;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    /**
     * A new staff member gets a random password and an e-mail link to set
     * their own, then logs in at /admin. An existing customer account with
     * the same e-mail is given the role instead of a second account.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('addStaff')
                ->label('Tambah staf')
                ->icon(Heroicon::OutlinedUserPlus)
                ->modalDescription('Staf baharu menerima e-mel untuk menetapkan kata laluan, kemudian log masuk di /admin.')
                ->schema([
                    TextInput::make('name')->label('Nama')->required()->maxLength(255),
                    TextInput::make('email')->label('E-mel')->email()->required()->maxLength(255),
                    Select::make('role')->label('Peranan')->options(StaffResource::roleOptions())->default(Role::Editor->value)->required(),
                ])
                ->modalSubmitActionLabel('Tambah dan hantar e-mel')
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

                    $isNew = $user === null;
                    $user ??= User::query()->create(['name' => $data['name'], 'email' => $email, 'password' => Str::password(32)]);
                    $user->forceFill(['role' => $data['role'], 'email_verified_at' => $user->email_verified_at ?? now()])->save();

                    if ($isNew) {
                        Password::sendResetLink(['email' => $email]);
                    }

                    Notification::make()
                        ->title($isNew ? 'Staf ditambah. E-mel untuk menetapkan kata laluan telah dihantar.' : 'Akaun sedia ada diberi akses staf. Mereka log masuk dengan kata laluan sendiri di /admin.')
                        ->success()->send();
                }),
        ];
    }
}
