<?php

namespace App\Filament\Resources\Books\Actions;

use App\Enums\AgeGroup;
use App\Services\BlogBooks;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * "Tarik banyak dari blog": staff search the book blog, tick several posts
 * and each becomes a draft book with its title, author, synopsis, categories
 * and cover. Posts that are already books are left out of the search, so
 * nothing is imported twice.
 */
class ImportManyFromBlogAction
{
    /** Each post takes a few seconds (post and cover), so a batch stays well within a web request. */
    public const MAX_PER_BATCH = 10;

    public static function make(): Action
    {
        return Action::make('importManyFromBlog')
            ->label('Tarik banyak dari blog')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->modalHeading('Tarik beberapa buku dari blog')
            ->modalDescription('Cari dan pilih sehingga '.self::MAX_PER_BATCH.' buku sekali gus. Setiap satu dicipta sebagai draf dengan tajuk, penulis, sinopsis, kategori dan kaver. Buku yang sudah ditarik tidak dipaparkan lagi. Selepas itu, buka setiap buku untuk semak dan muat naik PDF.')
            ->modalSubmitActionLabel('Cipta draf')
            ->schema([
                Select::make('posts')->label('Buku dari blog')
                    ->multiple()
                    ->placeholder('Taip tajuk, kemudian pilih. Boleh cari beberapa kali.')
                    ->searchable()
                    ->searchDebounce(500)
                    ->searchingMessage('Mencari di blog…')
                    ->noSearchResultsMessage('Tiada buku baharu dijumpai. Mungkin sudah ditarik, atau cuba perkataan lain.')
                    ->getSearchResultsUsing(function (string $search): array {
                        try {
                            $results = BlogBooks::search($search);
                        } catch (Throwable $e) {
                            report($e);

                            return [];
                        }

                        return array_diff_key($results, BlogBooks::importedIds(array_map('strval', array_keys($results))));
                    })
                    ->getOptionLabelsUsing(fn (array $values) => collect($values)
                        ->mapWithKeys(fn ($id) => [$id => BlogBooks::label((string) $id) ?? (string) $id])->all())
                    ->maxItems(self::MAX_PER_BATCH)
                    ->required(),
                Select::make('age_group')->label('Umur pembaca (untuk semua buku ini)')
                    ->options(AgeGroup::class)->required()->native(false)
                    ->helperText('Boleh diubah setiap buku kemudian.'),
            ])
            ->action(function (array $data): void {
                $age = $data['age_group'] instanceof AgeGroup ? $data['age_group']->value : (string) $data['age_group'];
                $done = [];
                $failed = [];
                foreach (array_map('strval', $data['posts']) as $id) {
                    $label = BlogBooks::label($id) ?? $id;
                    try {
                        $result = BlogBooks::import($id, $age);
                        $done[] = e($result['book']->title).($result['notes'] ? ' <span style="opacity:.7">('.e(implode(', ', $result['notes'])).')</span>' : '');
                    } catch (Throwable $e) {
                        report($e);
                        $failed[] = e($label).': '.e(str_starts_with($e->getMessage(), 'Sudah ada') ? 'sudah ada' : 'tidak dapat dibaca');
                    }
                }

                $body = [];
                if ($done) {
                    $body[] = '<strong>'.count($done).' draf dicipta:</strong><br>'.implode('<br>', $done);
                }
                if ($failed) {
                    $body[] = '<strong>Tidak dicipta:</strong><br>'.implode('<br>', $failed);
                }
                $body[] = 'Buka setiap draf untuk semak, muat naik PDF, kemudian hantar untuk semakan syariah.';

                Notification::make()
                    ->status($done ? 'success' : 'danger')
                    ->title($done ? 'Buku ditarik dari blog' : 'Tiada buku dicipta')
                    ->body(new HtmlString(implode('<br><br>', $body)))
                    ->persistent()->send();
            });
    }
}
