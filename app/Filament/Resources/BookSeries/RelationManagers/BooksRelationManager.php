<?php

namespace App\Filament\Resources\BookSeries\RelationManagers;

use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\DissociateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The books of a series; drag to set the reading order (Buku 1, 2, 3 …). */
class BooksRelationManager extends RelationManager
{
    protected static string $relationship = 'books';

    protected static ?string $inverseRelationship = 'series';

    protected static ?string $title = 'Buku dalam siri (seret untuk susun)';

    protected static ?string $modelLabel = 'buku';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('series_position')
            ->defaultSort('series_position')
            ->paginated(false)
            ->columns([
                TextColumn::make('series_position')->label('Buku ke-')->placeholder('-'),
                ImageColumn::make('cover_path')->label('Kaver')->disk('public')->imageHeight(56),
                TextColumn::make('title')->label('Tajuk')->description(fn (Book $record) => $record->author),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->headerActions([
                AssociateAction::make()->label('Tambah buku')
                    ->recordSelectSearchColumns(['title'])
                    ->recordSelectOptionsQuery(fn ($query) => $query->whereNull('book_series_id'))
                    ->after(function (Model $record) {
                        // A new book goes to the end of the series.
                        $last = (int) $this->getOwnerRecord()->books()->whereKeyNot($record->getKey())->max('series_position');
                        $record->update(['series_position' => $last + 1]);
                    }),
            ])
            ->recordActions([
                Action::make('edit')->label('Buka')->url(fn (Book $record) => BookResource::getUrl('edit', ['record' => $record])),
                DissociateAction::make()->label('Keluarkan')
                    ->after(fn (Book $record) => $record->update(['series_position' => null])),
            ]);
    }
}
