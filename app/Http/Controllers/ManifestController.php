<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCatalogOpen;
use App\Services\VideoShowcase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The web app manifest, so ASUH Digital installs on phones, tablets and
 * laptops like an app. Built per request for the visitor's language and
 * for the long-press shortcuts that are open (Mod Anak, Video, Katalog).
 */
class ManifestController extends Controller
{
    public function __invoke(Request $request, VideoShowcase $videos): JsonResponse
    {
        $user = $request->user();
        $shortcut = fn (string $name, string $url, string $description) => [
            'name' => $name,
            'short_name' => $name,
            'description' => $description,
            'url' => $url,
            'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png']],
        ];

        // Mod Anak and the catalogue open together (Phase C pages follow catalog_open).
        $catalogOpen = EnsureCatalogOpen::isOpenFor($user);
        $shortcuts = array_values(array_filter([
            $catalogOpen ? $shortcut(__('Mod Anak'), route('account.children', absolute: false), __('Pilih anak dan buka rak bukunya')) : null,
            $videos->isOpenFor($user) ? $shortcut(__('Video'), route('videos.index', absolute: false), __('Video animasi ASUH')) : null,
            $catalogOpen ? $shortcut(__('Katalog'), route('catalog.index', absolute: false), __('Semua buku ASUH')) : null,
        ]));

        return response()->json([
            'id' => '/',
            'name' => 'ASUH Digital',
            'short_name' => 'ASUH',
            'description' => __('Rak buku Islamik digital untuk anak 6–13 tahun. Jom Jadi Baik!'),
            'lang' => app()->getLocale(),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => '#F7F5FF',
            'theme_color' => '#3A1F6E',
            'categories' => ['books', 'education', 'kids'],
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => $shortcuts,
        ], options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'private, max-age=3600');
    }
}
