@unless (App\Models\Setting::get('catalog_open'))
    {{-- Only staff reach these pages while the catalogue is closed. --}}
    <div role="note" class="mb-6 rounded-xl border-2 border-oren bg-oren/15 px-4 py-3 text-sm font-semibold">
        Pratonton staf: katalog belum dibuka kepada orang ramai. Buka di panel admin → Tetapan.
    </div>
@endunless
