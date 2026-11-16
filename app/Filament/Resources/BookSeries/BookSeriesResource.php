<?php

namespace App\Filament\Resources\BookSeries;

use App\Filament\Resources\BookSeries\Pages\CreateBookSeries;
use App\Filament\Resources\BookSeries\Pages\EditBookSeries;
use App\Filament\Resources\BookSeries\Pages\ListBookSeries;
use App\Filament\Resources\BookSeries\RelationManagers\BooksRelationManager;
use App\Models\BookSeries;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Book series: books told over several volumes. Books join a series from
 * the book form (or the bulk blog import), and their reading order is set
 * by dragging them on the series page.
 */
class BookSeriesResource extends Resource
{
    protected static ?string $model = BookSeries::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Kandungan';

    protected static ?string $navigationLabel = 'Siri buku';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'siri';

    protected static ?string $pluralModelLabel = 'Siri buku';

    protected static ?string $slug = 'siri';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Nama siri')->required()->maxLength(150)
                    ->helperText('Contoh: Siri Komik Godaan Syaitan, Kisah Menarik 25 Rasul.'),
                TextInput::make('name_en')->label('Nama siri (English, pilihan)')->maxLength(150),
                Textarea::make('description')->label('Pengenalan siri (pilihan)')->rows(3)->maxLength(2000)
                    ->helperText('Dipaparkan di halaman siri.'),
                Textarea::make('description_en')->label('Pengenalan siri (English, pilihan)')->rows(3)->maxLength(2000),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Siri')->searchable(),
                TextColumn::make('books_count')->label('Buku')->counts('books'),
                TextColumn::make('updated_at')->label('Dikemas kini')->since(),
            ])
            ->recordActions([
                EditAction::make(),
                // Deleting a series only takes the books out of it; the books stay.
                DeleteAction::make()->modalDescription('Buku dalam siri ini tidak dipadam, cuma dikeluarkan daripada siri.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [BooksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookSeries::route('/'),
            'create' => CreateBookSeries::route('/create'),
            'edit' => EditBookSeries::route('/{record}/edit'),
        ];
    }
}
