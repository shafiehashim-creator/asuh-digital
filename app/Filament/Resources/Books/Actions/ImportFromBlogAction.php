<?php

namespace App\Filament\Resources\Books\Actions;

use App\Models\Book;
use App\Services\BlogBooks;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * "Tarik dari blog": staff type a book title, pick the matching post on the
 * Galeri Ilmu book blog, and the form is filled with its title, author,
 * synopsis, categories and cover. Nothing is saved until they press Save,
 * so they can check and correct everything first.
 */
class ImportFromBlogAction
{
    public static function make(): Action
    {
        return Action::make('importFromBlog')
            ->label('Tarik dari blog')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->modalHeading('Tarik maklumat buku dari blog')
            ->modalDescription(fn () => 'Taip tajuk buku untuk mencari di '.parse_url(BlogBooks::blogUrl(), PHP_URL_HOST).'. Tajuk, penulis, sinopsis, kategori dan kaver akan diisi dalam borang. Semak dahulu sebelum simpan. PDF dimuat naik seperti biasa.')
            ->modalSubmitActionLabel('Isi borang')
            ->schema([
                Select::make('post')->label('Tajuk buku')
                    ->placeholder('Taip sekurang-kurangnya 2 huruf')
                    ->searchable()
                    ->searchDebounce(500)
                    ->searchingMessage('Mencari di blog…')
                    ->noSearchResultsMessage('Tiada buku dijumpai. Cuba perkataan lain dari tajuk.')
                    ->getSearchResultsUsing(function (string $search): array {
                        try {
                            $results = BlogBooks::search($search);
                        } catch (Throwable $e) {
                            report($e);

                            return [];
                        }
                        $imported = BlogBooks::importedIds(array_map('strval', array_keys($results)));

                        return collect($results)->map(fn (string $label, $id) => isset($imported[$id]) ? "{$label} — ✓ sudah ada" : $label)->all();
                    })
                    ->getOptionLabelUsing(fn ($value) => BlogBooks::label((string) $value) ?? (string) $value)
                    ->required(),
            ])
            ->action(function (array $data, Component $livewire): void {
                $id = (string) $data['post'];
                $current = $livewire instanceof EditRecord ? $livewire->getRecord()->getKey() : null;
                $existing = Book::query()->where('blog_post_id', $id)->when($current, fn ($q) => $q->whereKeyNot($current))->first();
                if ($existing) {
                    Notification::make()->warning()->title('Buku ini sudah ada')
                        ->body("“{$existing->title}” sudah ditarik dari catatan blog ini. Buka buku itu untuk menyuntingnya.")->send();

                    return;
                }

                try {
                    $book = BlogBooks::find($id);
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->danger()->title('Tidak dapat membaca blog')
                        ->body('Cuba lagi sebentar. Jika masih gagal, isi borang secara manual.')->send();

                    return;
                }

                $notes = [];
                $cover = null;
                if ($book['cover_url']) {
                    try {
                        $cover = BlogBooks::downloadCover($book['cover_url'], $book['title']);
                    } catch (Throwable $e) {
                        report($e);
                        $notes[] = 'Kaver tidak dapat dimuat turun. Sila muat naik sendiri.';
                    }
                } else {
                    $notes[] = 'Tiada kaver dalam catatan blog.';
                }

                $livewire->data['title'] = $book['title'];
                $livewire->data['blog_post_id'] = $id;
                if ($book['author']) {
                    $livewire->data['author'] = $book['author'];
                }
                if ($book['synopsis']) {
                    $livewire->data['synopsis'] = $book['synopsis'];
                } else {
                    $notes[] = 'Sinopsis tidak dijumpai. Sila tulis sendiri.';
                }
                if ($book['category_ids']) {
                    $livewire->data['categories'] = array_values(array_unique([...($livewire->data['categories'] ?? []), ...$book['category_ids']]));
                }
                if ($cover) {
                    $livewire->data['cover_path'] = [(string) Str::uuid() => $cover['path']];
                    $size = "{$cover['width']} × {$cover['height']} piksel";
                    $notes[] = $cover['width'] < BlogBooks::SMALL_COVER_WIDTH
                        ? "Kaver kecil ({$size}). Gantikan dengan versi yang dipertingkat supaya tidak kabur."
                        : "Kaver {$size}.";
                }

                $facts = array_filter([
                    $book['print_price'] ? "harga buku cetak {$book['print_price']} (harga e-book tidak diisi)" : null,
                    $book['pages'] ? "{$book['pages']} muka surat" : null,
                    $book['isbn'] ? "ISBN {$book['isbn']}" : null,
                    $book['code'] ? "kod {$book['code']}" : null,
                ]);
                if ($facts) {
                    $notes[] = 'Dari blog: '.implode(', ', $facts).'.';
                }
                if (! $book['category_ids']) {
                    $notes[] = 'Kategori tidak dapat diteka. Sila pilih sendiri.';
                }
                $notes[] = 'Pilih umur pembaca, semak semua medan, kemudian simpan.';

                Notification::make()->success()->title('Borang diisi dari blog')
                    ->body(new HtmlString(implode('<br><br>', array_map('e', $notes))))
                    ->persistent()->send();
            });
    }
}
