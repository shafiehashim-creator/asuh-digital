<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pulls book details from the Galeri Ilmu book blog (a Blogger site) so
 * staff do not have to copy and paste them: search posts by title through
 * the blog's public JSON feed, then read the title, author, synopsis and
 * cover of the post they pick. Only the cover and text are on the blog; the
 * PDF is still uploaded by hand.
 */
class BlogBooks
{
    /** Covers narrower than this look soft on large screens and should be redone. */
    public const SMALL_COVER_WIDTH = 1000;

    /** Downloaded covers are shrunk to at most this height (the form does the same for uploads). */
    private const MAX_COVER_HEIGHT = 1600;

    public static function blogUrl(): string
    {
        return rtrim((string) config('services.book_blog.url'), '/');
    }

    /** @return array<string, string> post id => post title, newest first */
    public static function search(string $title): array
    {
        $title = trim($title);
        if (mb_strlen($title) < 2) {
            return [];
        }

        $response = Http::timeout(10)->acceptJson()->get(self::blogUrl().'/feeds/posts/default', [
            'alt' => 'json',
            'max-results' => 15,
            'q' => $title,
        ])->throw();

        $results = [];
        foreach ($response->json('feed.entry') ?? [] as $entry) {
            $id = self::postId($entry);
            if ($id !== null) {
                $results[$id] = self::cleanTitle($entry['title']['$t'] ?? '');
            }
        }

        // The select shows the label of the picked post again after the search.
        foreach ($results as $id => $label) {
            Cache::put("blog-book:{$id}", $label, now()->addHour());
        }

        return $results;
    }

    public static function label(string $id): ?string
    {
        return Cache::get("blog-book:{$id}");
    }

    /**
     * @return array{url: string, title: string, author: ?string, synopsis: ?string, isbn: ?string,
     *     code: ?string, pages: ?int, print_price: ?string, labels: list<string>, category_ids: list<int>, cover_url: ?string}
     */
    public static function find(string $id): array
    {
        if (! preg_match('/^\d{5,25}$/', $id)) {
            throw new RuntimeException('Bad post id.');
        }

        $entry = Http::timeout(10)->acceptJson()
            ->get(self::blogUrl()."/feeds/posts/default/{$id}", ['alt' => 'json'])
            ->throw()->json('entry');

        if (! is_array($entry)) {
            throw new RuntimeException('Post not found.');
        }

        return self::parse($entry);
    }

    /** @return array<string, mixed> see find() */
    public static function parse(array $entry): array
    {
        $html = (string) ($entry['content']['$t'] ?? '');
        $lines = self::lines($html);
        $field = fn (string $names) => self::field($lines, $names);

        $labels = collect($entry['category'] ?? [])->pluck('term')->filter()->map(fn ($t) => trim($t))->values()->all();
        $kategori = $field('kategori');
        $tebal = $field('tebal|halaman|muka surat');
        $harga = $field('harga');

        $url = collect($entry['link'] ?? [])->firstWhere('rel', 'alternate')['href'] ?? self::blogUrl();

        return [
            'url' => $url,
            'title' => self::bookTitle($entry, $lines),
            'author' => ($author = $field('penulis|pengarang|karya')) ? self::tidyCase($author) : null,
            'synopsis' => self::synopsis($lines),
            'isbn' => $field('isbn'),
            'code' => $field('kod'),
            'pages' => $tebal && preg_match('/(\d{1,4})/', $tebal, $m) ? (int) $m[1] : null,
            'print_price' => $harga && preg_match('/RM\s*([\d.,]+)/i', $harga, $m) ? 'RM'.rtrim($m[1], '.,') : null,
            'labels' => $labels,
            'category_ids' => self::categoryIds([...$labels, ...($kategori ? [$kategori] : [])]),
            'cover_url' => self::coverUrl($html),
        ];
    }

    /**
     * Saves the cover to the public disk, shrunk to the size the form uses.
     *
     * @return array{path: string, width: int, height: int} the size of the original
     */
    public static function downloadCover(string $url, string $name): array
    {
        $bytes = Http::timeout(30)->get($url)->throw()->body();
        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new RuntimeException('The cover is not a JPG, PNG or WEBP image.');
        }
        [$width, $height] = $info;

        $extension = image_type_to_extension($info[2], false) === 'jpeg' ? 'jpg' : image_type_to_extension($info[2], false);
        if ($height > self::MAX_COVER_HEIGHT && function_exists('imagecreatefromstring') && ($image = @imagecreatefromstring($bytes))) {
            $scaled = imagescale($image, (int) round($width * self::MAX_COVER_HEIGHT / $height), self::MAX_COVER_HEIGHT, IMG_BICUBIC);
            ob_start();
            imagejpeg($scaled, null, 88);
            $bytes = (string) ob_get_clean();
            $extension = 'jpg';
        }

        $path = 'covers/'.Str::slug(Str::limit($name, 60, '')).'-'.Str::lower(Str::random(6)).'.'.$extension;
        Storage::disk('public')->put($path, $bytes, 'public');

        return ['path' => $path, 'width' => $width, 'height' => $height];
    }

    private static function postId(array $entry): ?string
    {
        return preg_match('/post-(\d+)$/', (string) ($entry['id']['$t'] ?? ''), $m) ? $m[1] : null;
    }

    /** @return list<string> the post as plain text lines */
    private static function lines(string $html): array
    {
        $text = str_replace(["\r", "\n"], ' ', $html);
        $text = preg_replace('#<(br|/p|/div|/h\d|/li)\b[^>]*>#i', "\n", $text);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return collect(explode("\n", $text))
            ->map(fn (string $line) => trim((string) preg_replace('/\s+/u', ' ', $line)))
            ->filter(fn (string $line) => $line !== '')
            ->values()->all();
    }

    /** The value of a "Name: value" line, e.g. "Penulis: Pak Cik Bad". */
    private static function field(array $lines, string $names): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^(?:'.$names.')\s*:\s*(.+)$/iu', $line, $m)) {
                return trim($m[1]) ?: null;
            }
        }

        return null;
    }

    private static function bookTitle(array $entry, array $lines): string
    {
        // Some posts start with "Judul: ..." which is better than the post title.
        foreach ($lines as $line) {
            if (preg_match('/^(?:judul|tajuk)\s*:\s*(.+)$/iu', $line, $m)) {
                return self::tidyCase(trim($m[1]));
            }
        }

        return self::tidyCase(self::cleanTitle($entry['title']['$t'] ?? ''));
    }

    private static function cleanTitle(string $title): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** "YEAH, DAH SOLAT 2" reads better as "Yeah, Dah Solat 2". */
    private static function tidyCase(string $text): string
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $text);

        return $letters !== '' && mb_strtoupper($letters) === $letters
            ? mb_convert_case(mb_strtolower($text), MB_CASE_TITLE, 'UTF-8')
            : $text;
    }

    private static function synopsis(array $lines): ?string
    {
        $start = null;
        $first = '';
        foreach ($lines as $i => $line) {
            if (preg_match('/^(?:sinopsis|detail|blurb|blurp|ringkasan|tentang buku)\b\s*:?\s*(.*)$/iu', $line, $m)) {
                $start = $i;
                $first = trim($m[1]);
                break;
            }
        }

        // No heading: the text starts after the last "Name: value" line near the top.
        if ($start === null) {
            foreach (array_slice($lines, 0, 15, true) as $i => $line) {
                if (preg_match('/^[\p{L} ]{2,20}:/u', $line)) {
                    $start = $i;
                }
            }
        }
        if ($start === null) {
            return null;
        }

        $paragraphs = array_values(array_filter([$first, ...array_slice($lines, $start + 1)]));
        $text = trim(implode("\n\n", $paragraphs));

        return $text === '' ? null : Str::limit($text, 3000, '…');
    }

    /** The full-size cover: the first Blogger image, at its original size (s0). */
    private static function coverUrl(string $html): ?string
    {
        if (preg_match('#<a\b[^>]*href="([^"]+)"[^>]*>\s*<img#i', $html, $m) && self::isBloggerImage($m[1])) {
            $url = $m[1];
        } elseif (preg_match('#<img\b[^>]*src="([^"]+)"#i', $html, $m) && self::isBloggerImage($m[1])) {
            $url = $m[1];
        } else {
            return null;
        }

        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = (string) preg_replace('#/(?:s|w|h)\d+(?:-[a-z0-9]+)*/#', '/s0/', $url, 1);
        $url = (string) preg_replace('#=s\d+[^/]*$#', '=s0', $url);

        return str_starts_with($url, '//') ? 'https:'.$url : $url;
    }

    private static function isBloggerImage(string $url): bool
    {
        $host = parse_url(str_starts_with($url, '//') ? 'https:'.$url : $url, PHP_URL_HOST);

        return is_string($host) && (str_ends_with($host, '.googleusercontent.com') || preg_match('/^\d?\.?bp\.blogspot\.com$/', $host) || str_ends_with($host, '.bp.blogspot.com'));
    }

    /** @param list<string> $words blog labels and the "Kategori:" line */
    private static function categoryIds(array $words): array
    {
        $haystack = mb_strtolower(implode(' | ', $words));

        return Category::query()->get(['id', 'name'])
            ->filter(fn (Category $c) => $haystack !== '' && str_contains($haystack, mb_strtolower($c->name)))
            ->pluck('id')->values()->all();
    }
}
