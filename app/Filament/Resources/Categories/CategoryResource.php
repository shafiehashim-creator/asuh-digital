<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Kandungan';

    protected static ?string $navigationLabel = 'Kategori';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'kategori';

    protected static ?string $pluralModelLabel = 'Kategori';

    protected static ?string $slug = 'kategori';

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nama')->required()->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create'
                        ? $set('slug', Str::slug((string) $state))
                        : null),
                TextInput::make('slug')->label('Slug (untuk URL)')->required()->maxLength(100)
                    ->unique(ignoreRecord: true)->alphaDash(),
                TextInput::make('sort_order')->label('Susunan')->integer()->minValue(0)->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('books_count')->label('Buku')->counts('books'),
            ])
            ->recordActions([
                EditAction::make(),
                // A category in use cannot be deleted (book_category restricts it).
                DeleteAction::make()->visible(fn (Category $record) => $record->books()->doesntExist()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCategories::route('/'),
        ];
    }
}
