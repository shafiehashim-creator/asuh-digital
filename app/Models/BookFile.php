<?php

namespace App\Models;

use App\Enums\BookEdition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['book_id', 'edition', 'path', 'page_count', 'size_bytes'])]
class BookFile extends Model
{
    /** Private disk: storage/app/private, served only by ReaderController. */
    public const DISK = 'local';

    protected function casts(): array
    {
        return [
            'edition' => BookEdition::class,
            'page_count' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
