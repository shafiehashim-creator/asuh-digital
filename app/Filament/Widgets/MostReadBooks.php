<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Families per book, counted when a parent opens the reader (staff are not
 * counted). Not financial, so editors see it too.
 */
class MostReadBooks extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Buku paling banyak dibaca';

    public static function canView(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Book::query()
                ->published()
                ->withCount([
                    'readers',
                    'readers as recent_readers_count' => fn (Builder $q) => $q->where('book_readers.last_read_at', '>=', now()->subDays(30)),
                ])
                ->whereHas('readers')
                ->orderByDesc('readers_count')
                ->orderBy('title'))
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Belum ada bacaan')
            ->emptyStateDescription('Senarai ini terisi apabila ibu bapa mula membuka buku dalam pembaca.')
            ->columns([
                TextColumn::make('title')->label('Buku')
                    ->url(fn (Book $record) => BookResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('readers_count')->label('Keluarga membaca')->numeric()->alignEnd(),
                TextColumn::make('recent_readers_count')->label('30 hari lalu')->numeric()->alignEnd(),
            ]);
    }
}
