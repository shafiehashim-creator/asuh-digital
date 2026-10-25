<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use App\Models\Invitation;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    protected static ?string $title = 'Jemputan';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('email')->label('E-mel'),
                TextColumn::make('role')->label('Peranan')->badge(),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Invitation $record) => match (true) {
                        $record->accepted_at !== null => 'Diterima',
                        $record->expires_at->isPast() => 'Tamat tempoh',
                        default => 'Menunggu',
                    }),
                TextColumn::make('created_at')->label('Dihantar')->date('j M Y'),
            ])
            ->recordActions([DeleteAction::make()->label('Batal')->visible(fn (Invitation $record) => $record->accepted_at === null)])
            ->emptyStateHeading('Tiada jemputan');
    }
}
