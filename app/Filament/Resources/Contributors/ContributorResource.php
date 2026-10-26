<?php

namespace App\Filament\Resources\Contributors;

use App\Filament\Resources\Contributors\Pages\ManageContributors;
use App\Models\Contributor;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Writers and illustrators who receive royalties. Bank details: Super Admin only. */
class ContributorResource extends Resource
{
    protected static ?string $model = Contributor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Kandungan';

    protected static ?string $navigationLabel = 'Penulis dan ilustrator';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'penerima royalti';

    protected static ?string $pluralModelLabel = 'Penulis dan ilustrator';

    protected static ?string $slug = 'penulis';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')->label('Nama')->required()->maxLength(255),
                TextInput::make('email')->label('E-mel')->email()->maxLength(255),
                TextInput::make('phone')->label('Telefon')->tel()->maxLength(30),
                TextInput::make('bank_name')->label('Nama bank')->maxLength(100),
                TextInput::make('bank_account_name')->label('Nama pemegang akaun')->maxLength(150),
                TextInput::make('bank_account_number')->label('Nombor akaun')->maxLength(40),
                Textarea::make('notes')->label('Catatan (contohnya rujukan kontrak)')->rows(2)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->description(fn (Contributor $record) => $record->email),
                TextColumn::make('books_count')->label('Buku')->counts('books'),
                TextColumn::make('bank_account_number')->label('Akaun bank')
                    ->state(fn (Contributor $record) => trim("{$record->bank_name} {$record->bank_account_number}") ?: null)->placeholder('-'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Contributor $record) => $record->books()->doesntExist()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContributors::route('/'),
        ];
    }
}
