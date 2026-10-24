<?php

namespace App\Services;

use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Models\Book;
use App\Models\SyariahReview;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Draft -> review -> published. The syariah rule itself lives in Book::booted(),
 * so even a mistake here cannot publish an unreviewed book.
 */
class BookWorkflow
{
    public function submitForReview(Book $book): SyariahReview
    {
        if ($book->files()->doesntExist()) {
            throw new InvalidArgumentException('Muat naik sekurang-kurangnya satu fail PDF sebelum menghantar untuk semakan.');
        }

        return DB::transaction(function () use ($book) {
            $book->update(['status' => BookStatus::Review]);

            return SyariahReview::query()->create(['book_id' => $book->id, 'status' => ReviewStatus::Pending]);
        });
    }

    public function review(Book $book, User $reviewer, bool $approved, ?string $notes = null): SyariahReview
    {
        if (! $reviewer->isStaff()) {
            throw new InvalidArgumentException('Hanya admin atau editor boleh membuat semakan syariah.');
        }

        if (! $approved && blank($notes)) {
            throw new InvalidArgumentException('Nyatakan apa yang perlu dibetulkan.');
        }

        return DB::transaction(function () use ($book, $reviewer, $approved, $notes) {
            $review = $book->latestReview()->first();

            if ($review?->status !== ReviewStatus::Pending) {
                $review = new SyariahReview(['book_id' => $book->id]);
            }

            $review->fill([
                'reviewer_id' => $reviewer->id,
                'status' => $approved ? ReviewStatus::Approved : ReviewStatus::Rejected,
                'notes' => $notes,
                'reviewed_at' => now(),
            ])->save();

            // A failed review sends the book back to the editors.
            if (! $approved) {
                $book->update(['status' => BookStatus::Draft]);
            }

            return $review;
        });
    }

    public function publish(Book $book, ?Carbon $at = null): void
    {
        $book->update(['status' => BookStatus::Published, 'published_at' => $at ?? now()]);
    }

    public function unpublish(Book $book): void
    {
        $book->update(['status' => BookStatus::Draft]);
    }
}
