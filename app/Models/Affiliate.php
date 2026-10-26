<?php

namespace App\Models;

use App\Enums\AffiliateStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['promotion_channels', 'bank_name', 'bank_account_name', 'bank_account_number'])]
class Affiliate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'commission_percent' => 'float',
            'approved_at' => 'datetime',
        ];
    }

    /** Approved affiliate for a typed code or ?ref= value. */
    public static function findActiveByCode(?string $code): ?self
    {
        $code = Str::upper(trim((string) $code));

        return $code === '' ? null : static::query()->where('code', $code)->where('status', AffiliateStatus::Approved)->first();
    }

    /** A short code from the name, e.g. AMINAH7K. */
    public static function newCode(string $name): string
    {
        $base = Str::upper(Str::limit(preg_replace('/[^A-Za-z]/', '', Str::ascii($name)) ?: 'ASUH', 8, ''));

        do {
            $code = $base.Str::upper(Str::random(2));
        } while (static::query()->where('code', $code)->exists() || Coupon::findByCode($code) || Institution::findByParentCode($code));

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isActive(): bool
    {
        return $this->status === AffiliateStatus::Approved;
    }

    public function effectivePercent(): float
    {
        return $this->commission_percent ?? (float) Setting::get('affiliate_commission_percent');
    }

    public function link(): string
    {
        return url('/').'/?ref='.$this->code;
    }
}
