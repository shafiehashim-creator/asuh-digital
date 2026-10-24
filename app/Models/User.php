<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role?->canAccessAdminPanel() ?? false;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }

    public function preorders(): HasMany
    {
        return $this->hasMany(Preorder::class);
    }

    public function activePass(): ?Pass
    {
        return $this->passes()->active()->latest('ends_at')->first();
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Editor], true);
    }

    /**
     * CLAUDE.md: e-books open only with an active pass or a purchased
     * entitlement (institution licences come in Phase D). Staff may preview
     * any book, published or not, for editing and syariah review.
     */
    public function canReadBook(Book $book): bool
    {
        if ($this->isStaff()) {
            return true;
        }

        if (! $book->isPublished()) {
            return false;
        }

        return $this->passes()->active()->exists()
            || $this->entitlements()->where('book_id', $book->id)->exists();
    }
}
