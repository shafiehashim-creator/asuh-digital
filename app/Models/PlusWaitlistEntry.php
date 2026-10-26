<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One "Beritahu saya bila dibuka" sign-up for Langganan Plus. */
class PlusWaitlistEntry extends Model
{
    protected $table = 'plus_waitlist';

    protected $fillable = ['email', 'user_id', 'locale', 'notified_at'];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
