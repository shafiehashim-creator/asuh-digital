<x-filament-panels::page>
    @vite(['resources/js/page-prep.js'])

    <x-filament::section>
        <p style="margin: 0; line-height: 1.6;">
            Versi pantas menukar setiap halaman PDF kepada gambar ringan supaya buku dibuka dengan cepat di telefon.
            Penukaran berlaku dalam pelayar ini. <strong>Jangan tutup halaman ini</strong> sehingga semua fail siap.
            PDF asal kekal disimpan.
        </p>
    </x-filament::section>

    @forelse ($this->files() as $file)
        <x-filament::section :heading="'Fail '.$file->edition->getLabel()">
            <div data-prep-file
                data-auto="{{ $auto && ! $file->hasPageImages() ? '1' : '0' }}"
                data-pdf="{{ route('admin.pages.pdf', $file) }}"
                data-start="{{ route('admin.pages.start', $file) }}"
                data-store="{{ route('admin.pages.store', ['file' => $file, 'page' => '__PAGE__']) }}"
                data-finish="{{ route('admin.pages.finish', $file) }}"
                data-width="{{ App\Models\BookFile::PAGE_IMAGE_WIDTH }}"
                data-csrf="{{ csrf_token() }}">

                <p data-prep-status style="margin: 0 0 0.75rem; font-weight: 600;">
                    @if ($file->hasPageImages())
                        ✓ Versi pantas sedia: {{ $file->page_images }} halaman (dibuat {{ $file->pages_ready_at->translatedFormat('j M Y, g:i A') }}).
                    @else
                        Versi pantas belum dibuat. Pembaca masih menggunakan PDF penuh (lebih perlahan di telefon).
                    @endif
                </p>

                <div data-prep-bar hidden style="height: 0.75rem; border-radius: 9999px; background: rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 0.75rem;">
                    <div data-prep-fill style="height: 100%; width: 0; background: #1559D6; transition: width 0.2s;"></div>
                </div>

                <x-filament::button data-prep-run type="button" :color="$file->hasPageImages() ? 'gray' : 'primary'">
                    {{ $file->hasPageImages() ? 'Buat semula' : 'Buat versi pantas' }}
                </x-filament::button>
            </div>
        </x-filament::section>
    @empty
        <x-filament::section>
            <p style="margin: 0;">Buku ini belum ada fail PDF. Muat naik PDF di halaman buku dahulu.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
