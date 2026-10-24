<?php

namespace App\Filament\Resources\Books\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only history: reviews are written only through EditBook's actions.
 */
class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    protected static ?string $title = 'Sejarah semakan syariah';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')->label('Keputusan')->badge(),
                TextColumn::make('reviewer.name')->label('Penyemak')->placeholder('-'),
                TextColumn::make('notes')->label('Catatan')->wrap()->placeholder('-'),
                TextColumn::make('created_at')->label('Dihantar')->dateTime('j M Y, g:i A'),
                TextColumn::make('reviewed_at')->label('Disemak')->dateTime('j M Y, g:i A')->placeholder('-'),
            ])
            ->emptyStateHeading('Belum dihantar untuk semakan');
    }
}
