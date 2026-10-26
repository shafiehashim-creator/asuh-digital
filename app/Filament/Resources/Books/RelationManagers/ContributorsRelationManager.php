<?php

namespace App\Filament\Resources\Books\RelationManagers;

use App\Enums\ContributorRole;
use App\Models\Book;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Who receives this book's royalty and their share. Shares should add up
 * to 100%; the royalty report flags books where they do not.
 */
class ContributorsRelationManager extends RelationManager
{
    protected static string $relationship = 'contributors';

    protected static ?string $title = 'Penerima royalti';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->pivotFields());
    }

    /** @return array<int, mixed> */
    private function pivotFields(): array
    {
        return [
            Select::make('role')->label('Peranan')->options(ContributorRole::class)->default(ContributorRole::Writer->value)->required(),
            TextInput::make('share_percent')->label('Bahagian royalti (%)')->numeric()->minValue(0)->maxValue(100)->step(0.01)->default(100)->required(),
        ];
    }

    public function table(Table $table): Table
    {
        /** @var Book $book */
        $book = $this->getOwnerRecord();
        $total = (float) $book->contributors()->sum('book_contributor.share_percent');

        return $table
            ->recordTitleAttribute('name')
            ->description($book->contributors()->exists() && abs($total - 100) >= 0.01
                ? 'Jumlah bahagian kini '.rtrim(rtrim(number_format($total, 2), '0'), '.').'%. Pastikan jumlahnya 100%.'
                : 'Contoh: penulis 70%, ilustrator 30%. Jumlah mesti 100%.')
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('role')->label('Peranan')
                    ->formatStateUsing(fn ($state) => ContributorRole::tryFrom((string) $state)?->getLabel() ?? $state),
                TextColumn::make('share_percent')->label('Bahagian')->suffix('%'),
            ])
            ->headerActions([
                AttachAction::make()->label('Tambah penerima')->preloadRecordSelect()
                    ->schema(fn (AttachAction $action) => [$action->getRecordSelect()->label('Penulis atau ilustrator'), ...$this->pivotFields()]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()->label('Buang'),
            ])
            ->emptyStateHeading('Belum ada penerima royalti')
            ->emptyStateDescription('Tambah penulis atau ilustrator dahulu di Kandungan → Penulis dan ilustrator.');
    }
}
