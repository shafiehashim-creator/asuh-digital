<?php

namespace App\Filament\Resources\Staff;

use App\Enums\Role;
use App\Filament\Resources\Staff\Pages\ManageStaff;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
