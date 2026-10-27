<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'sort_order'])]
class Category extends Model
{
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** A picture for the category's shelf in Mod Anak, for children still learning to read. */
    public function emoji(): string
    {
        $name = mb_strtolower($this->name);

        return match (true) {
            str_contains($name, 'nabi') => '🌙',
            str_contains($name, 'sirah') => '🕌',
            str_contains($name, 'akhlak') => '💛',
            str_contains($name, 'doa') => '🤲',
            str_contains($name, 'komik') => '💬',
            str_contains($name, 'bergambar') => '🖼️',
            str_contains($name, 'novel') => '📚',
            default => '📖',
        };
    }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
