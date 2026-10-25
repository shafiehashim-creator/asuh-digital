<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only: people join only through invitations. */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Pentadbir dan guru';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('email')->label('E-mel')->copyable(),
                TextColumn::make('role')->label('Peranan')->badge(),
                TextColumn::make('created_at')->label('Menyertai')->date('j M Y'),
            ])
            ->emptyStateHeading('Belum ada yang menyertai');
    }
}
