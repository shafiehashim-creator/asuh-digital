<x-layouts.account title="Rak buku saya" heading="Rak buku saya" intro="Buku yang dibaca keluarga dan buku yang dibeli." :$activePass :$pendingPass :$shelfOpen :$plusUpgradePrice>
    <section aria-labelledby="shelf-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="shelf-heading" class="sr-only">Rak buku saya</h2>
            <a href="{{ route('catalog.index') }}" class="pautan text-sm">{{ $canReadAll ? 'Pilih buku lain dalam katalog' : 'Lihat katalog' }}</a>
        </div>

        @if ($canReadAll)
            <p class="mt-2 text-ink/80">Langganan Tahunan anda membuka <strong>semua buku</strong> dalam katalog.</p>
        @endif

        @if ($recentBooks->isNotEmpty())
            <h3 class="mt-5 font-display font-bold">Sambung membaca</h3>
            <div class="mt-3 grid grid-cols-3 gap-4 sm:grid-cols-6">
                @foreach ($recentBooks as $book)
                    <x-shelf-book :book="$book" />
                @endforeach
            </div>
        @endif

        @if ($ownedBooks->isNotEmpty())
            <h3 class="mt-5 font-display font-bold">Buku yang dibeli</h3>
            <div class="mt-3 grid grid-cols-3 gap-4 sm:grid-cols-6">
                @foreach ($ownedBooks as $book)
                    <x-shelf-book :book="$book" />
                @endforeach
            </div>
        @endif

        @if ($recentBooks->isEmpty() && $ownedBooks->isEmpty())
            <p class="mt-2 text-ink/80">
                {{ $canReadAll ? 'Belum ada buku yang dibuka. Pilih buku pertama untuk anak anda dalam katalog.' : 'Rak anda masih kosong. Buku yang dibeli atau dibaca akan dipaparkan di sini.' }}
            </p>
        @endif
    </section>
</x-layouts.account>
