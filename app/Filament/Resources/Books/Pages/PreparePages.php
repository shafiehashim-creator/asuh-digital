<?php

namespace App\Filament\Resources\Books\Pages;

use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use App\Models\BookFile;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * "Versi pantas": the admin's browser renders every PDF page to a light image
 * (resources/js/page-prep.js) and uploads it, so phones open books quickly.
 * Opened automatically after a book with a new PDF is saved.
 *
 * @property Book $record
 */
class PreparePages extends Page
{
    use InteractsWithRecord;

    protected static string $resource = BookResource::class;

    protected string $view = 'filament.books.prepare-pages';

    public bool $auto = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
        $this->auto = request()->boolean('auto');
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canAccess(), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Versi pantas: '.$this->record->title;
    }

    /** @return Collection<int, BookFile> */
    public function files(): Collection
    {
        return $this->record->files()->orderBy('edition')->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali ke buku')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn () => EditBook::getUrl(['record' => $this->record])),
        ];
    }
}
