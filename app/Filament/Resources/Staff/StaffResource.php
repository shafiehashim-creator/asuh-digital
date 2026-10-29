<?php

namespace App\Filament\Resources\Staff;

use App\Enums\Role;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * ASUH staff with panel access: Super Admins (everything) and editors
 * (books, videos, quizzes and reviews, no finances). Super Admin only.
 */
class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Staf admin';

    protected static ?int $navigationSort = 80;

    protected static ?string $modelLabel = 'staf';

    protected static ?string $pluralModelLabel = 'Staf admin';

    protected static ?string $slug = 'staf';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('role', [Role::Admin, Role::Editor]);
    }

    /** @return array<string, string> */
    public static function roleOptions(): array
    {
        return [
            Role::Admin->value => 'Super Admin: semua, termasuk kewangan, tetapan dan staf',
            Role::Editor->value => 'Editor: buku, video, kuiz dan semakan sahaja',
        ];
    }

    /**
     * How a staff member gets in (user decision 2026-10-29): a temporary
     * password the Super Admin passes on (WhatsApp, in person), changed at
     * first login, or an e-mail link to set one (valid 24 hours).
     *
     * @return list<Component|Field>
     */
    public static function accessFields(): array
    {
        return [
            Radio::make('access')->label('Cara log masuk kali pertama')
                ->options([
                    'password' => 'Saya tetapkan kata laluan sementara (paling mudah)',
                    'email' => 'Hantar e-mel pautan untuk menetapkan kata laluan (sah 24 jam)',
                ])
                ->default('password')->required()->live(),
            TextInput::make('temporary_password')->label('Kata laluan sementara')
                ->default(fn () => self::temporaryPassword())
                ->visible(fn (Get $get) => $get('access') === 'password')
                ->required(fn (Get $get) => $get('access') === 'password')
                ->minLength(10)->maxLength(64)
                ->copyable()
                ->helperText('Salin dan beri kepada staf (contohnya melalui WhatsApp). Mereka diminta menukarnya sebaik log masuk di /admin.'),
        ];
    }

    /** Easy to read out and type on a phone: no 0/O or 1/l/I. */
    public static function temporaryPassword(): string
    {
        $pick = fn (string $chars, int $n) => collect(range(1, $n))->map(fn () => $chars[random_int(0, strlen($chars) - 1)])->join('');

        return 'Asuh-'.$pick('abcdefghjkmnpqrstuvwxyz', 4).'-'.$pick('23456789', 4);
    }

    /** Gives the account its way in, and says what to pass on. */
    public static function grantAccess(User $user, array $data): void
    {
        if (($data['access'] ?? 'password') === 'password') {
            $user->forceFill(['password' => $data['temporary_password'], 'must_change_password' => true])->save();

            Notification::make()
                ->title('Beri maklumat log masuk ini kepada '.$user->name)
                ->body(new HtmlString(collect([
                    'Laman: '.url('/admin'),
                    'E-mel: '.$user->email,
                    'Kata laluan sementara: <strong>'.e($data['temporary_password']).'</strong>',
                    '',
                    'Mereka akan diminta menukar kata laluan sebaik log masuk. Maklumat ini tidak dipaparkan lagi.',
                ])->map(fn ($line) => str_starts_with($line, 'Kata') ? $line : e($line))->join('<br>')))
                ->success()->persistent()->send();

            return;
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        $status === Password::RESET_LINK_SENT
            ? Notification::make()->title('E-mel dihantar kepada '.$user->email)->body('Pautan sah 24 jam. Minta staf semak folder Spam/Promotions jika tidak nampak.')->success()->send()
            : Notification::make()->title('E-mel belum dihantar.')->body('E-mel baru sahaja dihantar kepada akaun ini. Tunggu seminit, atau guna kata laluan sementara.')->warning()->send();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('email')->label('E-mel')->searchable()->copyable(),
                TextColumn::make('role')->label('Peranan')->badge(),
                TextColumn::make('created_at')->label('Ditambah')->date('j M Y'),
            ])
            ->recordActions([
                Action::make('changeRole')
                    ->label('Tukar peranan')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->hidden(fn (User $record) => $record->is(auth()->user()))
                    ->fillForm(fn (User $record) => ['role' => $record->role->value])
                    ->schema([
                        Select::make('role')->label('Peranan')->options(self::roleOptions())->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        if ($record->role === Role::Admin && $data['role'] !== Role::Admin->value && self::lastAdmin($record)) {
                            Notification::make()->title('Mesti ada sekurang-kurangnya seorang Super Admin.')->danger()->send();

                            return;
                        }

                        $record->forceFill(['role' => $data['role']])->save();
                        Notification::make()->title('Peranan ditukar.')->success()->send();
                    }),
                Action::make('resetPassword')
                    ->label('Kata laluan baharu')
                    ->icon(Heroicon::OutlinedKey)
                    ->hidden(fn (User $record) => $record->is(auth()->user()))
                    ->modalDescription(fn (User $record) => 'Untuk '.$record->name.' yang belum dapat log masuk atau terlupa kata laluan.')
                    ->schema(fn () => self::accessFields())
                    ->modalSubmitActionLabel('Teruskan')
                    ->action(fn (User $record, array $data) => self::grantAccess($record, $data)),
                Action::make('removeAccess')
                    ->label('Buang akses')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->hidden(fn (User $record) => $record->is(auth()->user()))
                    ->requiresConfirmation()
                    ->modalDescription('Akaun ini tidak lagi boleh membuka panel admin. Ia kekal sebagai akaun biasa di laman, tanpa akses staf.')
                    ->action(function (User $record) {
                        if ($record->role === Role::Admin && self::lastAdmin($record)) {
                            Notification::make()->title('Mesti ada sekurang-kurangnya seorang Super Admin.')->danger()->send();

                            return;
                        }

                        $record->forceFill(['role' => Role::Parent])->save();
                        Notification::make()->title('Akses admin dibuang.')->success()->send();
                    }),
            ]);
    }

    private static function lastAdmin(User $user): bool
    {
        return User::query()->where('role', Role::Admin)->whereKeyNot($user->getKey())->doesntExist();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStaff::route('/'),
        ];
    }
}
