<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A writer or illustrator paid royalties on the books they worked on. */
#[Fillable(['name', 'email', 'phone', 'bank_name', 'bank_account_name', 'bank_account_number', 'notes'])]
class Contributor extends Model
{
    use HasFactory;

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)->withPivot(['role', 'share_percent'])->withTimestamps();
    }
}
