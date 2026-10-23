<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('asuh:create-admin')]
#[Description('Create a Super Admin account for the /admin panel')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $name = $this->ask('Nama');
        $email = $this->ask('E-mel');
        // secret() hides the input so the password never appears on screen or in logs.
        $password = $this->secret('Kata laluan (sekurang-kurangnya 12 aksara)');
        $confirmation = $this->secret('Taip semula kata laluan');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = Role::Admin;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Super Admin {$email} telah dicipta. Log masuk di /admin.");

        return self::SUCCESS;
    }
}
