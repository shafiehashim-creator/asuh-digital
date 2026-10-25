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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token', 'parent_pin'])]
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
            'parent_pin' => 'hashed',
            'weekly_report' => 'boolean',
            'weekly_report_sent_at' => 'datetime',
            'role' => Role::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role?->canAccessAdminPanel() ?? false;
    }

    public function hasParentPin(): bool
    {
        return filled($this->parent_pin);
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

    /** @return HasMany<Child, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Child::class)->orderBy('created_at')->orderBy('id');
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

    /** Books this family has opened in the reader (see Book::recordReader()). */
    public function readBooks(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_readers')->withPivot('first_read_at', 'last_read_at');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function isInstitutionAdmin(): bool
    {
        return $this->isInstitutionStaff() && $this->role === Role::InstitutionAdmin;
    }

    /** Institution administrators see every class; teachers their own. */
    public function canManageClassroom(Classroom $classroom): bool
    {
        return $this->isInstitutionStaff()
            && $classroom->institution_id === $this->institution_id
            && ($this->isInstitutionAdmin() || $classroom->teacher_id === $this->id);
    }

    /** Institution administrators and teachers (the /guru portal). */
    public function isInstitutionStaff(): bool
    {
        return ($this->role?->isInstitutionStaff() ?? false) && $this->institution_id !== null;
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
