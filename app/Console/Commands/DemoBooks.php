<?php

namespace App\Console\Commands;

use App\Enums\AgeGroup;
use App\Enums\BookEdition;
use App\Enums\BookStatus;
use App\Enums\ReviewStatus;
use App\Models\Book;
use App\Models\BookFile;
use App\Models\Category;
use App\Models\SyariahReview;
use App\Support\DemoPdf;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('asuh:demo-books')]
#[Description('Create demo books with generated PDFs (local development only)')]
class DemoBooks extends Command
{
    /** @var list<array{0: string, 1: string, 2: string, 3: AgeGroup, 4: string}> */
    private const BOOKS = [
        ['Anis Belajar Berkongsi', 'Akhlak', 'Pasukan ASUH', AgeGroup::SixToNine, 'Anis mempunyai sekotak pensel warna baharu. Bila kawannya terlupa membawa pensel, Anis belajar bahawa berkongsi membuatkan hati lebih gembira.'],
        ['Anas dan Si Penggoda', 'Komik', 'Pasukan ASUH', AgeGroup::SixToNine, 'Si Gemuk dan Si Kurus cuba memujuk Anas supaya bermain dan melupakan solat. Ikuti Anas memilih yang baik.'],
        ['Doa Sebelum Tidur', 'Doa harian', 'Pasukan ASUH', AgeGroup::SixToNine, 'Doa-doa ringkas sebelum tidur dan selepas bangun, dengan maksud yang mudah difahami anak.'],
        ['Kisah Nabi Yunus', 'Kisah nabi', 'Pasukan ASUH', AgeGroup::Both, 'Kisah kesabaran Nabi Yunus di dalam perut ikan, dan doa yang membawa harapan.'],
        ['Taman Rahsia Anis', 'Cerita bergambar', 'Pasukan ASUH', AgeGroup::SixToNine, 'Anis menanam benih kecil dan belajar tentang sabar serta bersyukur kepada Allah.'],
        ['Misteri di Sekolah Agama', 'Novel kanak-kanak', 'Pasukan ASUH', AgeGroup::TenToThirteen, 'Sebuah buku hilang dari perpustakaan sekolah. Anas dan rakan-rakan menyiasat dengan jujur dan adil.'],
        ['Hijrah ke Madinah', 'Sirah', 'Pasukan ASUH', AgeGroup::TenToThirteen, 'Perjalanan hijrah Rasulullah SAW dan para sahabat, diceritakan untuk pembaca muda.'],
        ['Jujur Itu Berani', 'Akhlak', 'Pasukan ASUH', AgeGroup::TenToThirteen, 'Bila tersilap memecahkan pasu, Anas perlu memilih: berbohong atau berterus terang.'],
    ];

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Arahan ini hanya untuk pembangunan lokal. Muat naik buku sebenar melalui panel admin.');

            return self::FAILURE;
        }

        $created = 0;

        foreach (self::BOOKS as [$title, $category, $author, $age, $synopsis]) {
            if (Book::query()->where('title', $title)->exists()) {
                continue;
            }

            $book = Book::query()->create([
                'title' => $title,
                'author' => $author,
                'synopsis' => $synopsis,
                'age_group' => $age,
            ]);
            $book->categories()->attach(Category::query()->where('name', $category)->value('id'));

            $path = "books/demo-{$book->slug}.pdf";
            $pages = random_int(6, 12);
            Storage::disk(BookFile::DISK)->put($path, DemoPdf::make($title, $pages));
            $book->files()->create(['edition' => BookEdition::Original, 'path' => $path, 'page_count' => $pages]);

            SyariahReview::query()->create([
                'book_id' => $book->id,
                'status' => ReviewStatus::Approved,
                'notes' => 'Data contoh.',
                'reviewed_at' => now(),
            ]);
            $book->update(['status' => BookStatus::Published, 'published_at' => now()->subMinutes(10 - $created)]);

            $created++;
        }

        $this->info("{$created} buku contoh dicipta.");

        return self::SUCCESS;
    }
}
