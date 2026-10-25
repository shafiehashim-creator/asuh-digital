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

    /** Set for pupils (Phase D), who come in with a class code instead of a parent's login. */
    private const PUPIL_KEY = 'child_mode_pupil';

    public function __construct(private Session $session) {}

    public function enter(Child $child): void
    {
        $this->session->put(self::SESSION_KEY, $child->id);
    }

    public function enterAsPupil(Child $pupil): void
    {
        $this->session->put(self::SESSION_KEY, $pupil->id);
        $this->session->put(self::PUPIL_KEY, true);
    }

    public function leave(): void
    {
        $this->session->forget([self::SESSION_KEY, self::PUPIL_KEY]);
    }

    public function isPupil(): bool
    {
        return (bool) $this->session->get(self::PUPIL_KEY);
    }

    public function isActive(): bool
    {
        return $this->session->has(self::SESSION_KEY);
    }

    /**
     * The child in Mod Anak: the parent's own child, or a pupil whose
     * institution still has an active licence.
     */
    public function child(?User $parent): ?Child
    {
        $id = $this->session->get(self::SESSION_KEY);

        if (! $id) {
            return null;
        }

        if ($this->isPupil()) {
            $pupil = Child::query()->whereKey($id)->whereNotNull('classroom_id')->with('classroom.institution')->first();

            return $pupil?->classroom->institution->activeLicense() ? $pupil : null;
        }

        return $parent?->children()->whereKey($id)->first();
    }

    /**
     * Books for the child's age that the family can open: all of them with a
     * pass, else the single books bought.
     *
     * @return Collection<int, Book>
     */
    public function shelf(Child $child): Collection
    {
        // An institution licence opens every published book for the class's age.
        if ($child->isPupil()) {
            return Book::query()->published()->forAge($child->age_group)->latest('published_at')->get();
        }

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
        if (! in_array($book->age_group, [$child->age_group, AgeGroup::Both], true)) {
            return false;
        }

        // Pupils: child() has already checked the licence.
        return $child->isPupil() ? $book->isPublished() : $child->user->canReadBook($book);
    }
}
