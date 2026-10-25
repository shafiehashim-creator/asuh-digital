<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    /**
     * For customers met outside the site (a fair, a phone call): the account
     * is made with a random password and the customer gets a link by e-mail
     * to set their own. Subscriptions and books are then added on their page.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createCustomer')
                ->label('Pelanggan baharu')
                ->icon(Heroicon::OutlinedUserPlus)
                ->modalDescription('Pelanggan akan menerima e-mel untuk menetapkan kata laluan sendiri. Selepas ini, tambah langganan atau e-book di halaman pelanggan.')
                ->schema([
                    TextInput::make('name')->label('Nama')->required()->maxLength(255),
                    TextInput::make('email')->label('E-mel')->email()->required()->maxLength(255)
                        ->rule(Rule::unique(User::class, 'email'))
                        ->validationMessages(['unique' => 'E-mel ini sudah mempunyai akaun. Cari pelanggan itu dalam senarai.']),
                    TextInput::make('phone')->label('No. telefon')->tel()->required()
                        ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                            if (! preg_match(Phone::PATTERN, (string) Phone::normalize($value))) {
                                $fail('No. telefon tidak sah. Contoh: 012-345 6789');
                            }
                        }),
                ])
                ->modalSubmitActionLabel('Cipta dan hantar e-mel')
                ->action(function (array $data) {
                    $customer = User::query()->create([
                        'name' => $data['name'],
                        'email' => Str::lower($data['email']),
                        'phone' => Phone::normalize($data['phone']),
                        'password' => Str::password(32),
                    ]);
                    // The admin met the customer; the link they use proves the address too.
                    $customer->forceFill(['email_verified_at' => now()])->save();

                    Password::sendResetLink(['email' => $customer->email]);

                    Notification::make()->title('Pelanggan dicipta. E-mel untuk menetapkan kata laluan telah dihantar.')->success()->send();
                    $this->redirect(CustomerResource::getUrl('view', ['record' => $customer]));
                }),
        ];
    }
}
