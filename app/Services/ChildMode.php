<?php

namespace App\Services;

use App\Enums\AgeGroup;
use App\Models\Book;
use App\Models\Child;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;

/**
 * Mod Anak: once a parent hands the device to a child, the session is locked
 * to that child's shelf and the reader until the parent gate is passed
 * (see LockChildMode). No prices, buy buttons or account settings.
 */
class ChildMode
{
    private const SESSION_KEY = 'child_mode';

    public function __construct(private Session $session) {}

    public function enter(Child $child): void
    {
        $this->session->put(self::SESSION_KEY, $child->id);
    }

    public function leave(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    public function isActive(): bool
    {
        return $this->session->has(self::SESSION_KEY);
    }

    /** The child in Mod Anak, if it still belongs to this parent. */
    public function child(?User $parent): ?Child
    {
        $id = $this->session->get(self::SESSION_KEY);

        return $id && $parent ? $parent->children()->whereKey($id)->first() : null;
    }

    /**
     * Books for the child's age that the family can open: all of them with a
     * pass, else the single books bought.
     *
     * @return Collection<int, Book>
     */
    public function shelf(Child $child): Collection
    {
        $parent = $child->user;

        return Book::query()->published()
            ->forAge($child->age_group)
            ->when(! $parent->isStaff() && ! $parent->passes()->active()->exists(),
                fn ($query) => $query->whereIn('id', $parent->entitlements()->select('book_id')))
            ->latest('published_at')
            ->get();
    }

    public function allows(Child $child, Book $book): bool
    {
        return in_array($book->age_group, [$child->age_group, AgeGroup::Both], true)
            && $child->user->canReadBook($book);
    }
}
