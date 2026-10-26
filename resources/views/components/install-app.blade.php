@props(['banner' => false])

{{--
    "Pasang app": a real install prompt on Chrome, Edge and Android, and
    Share > Add to Home Screen steps on iPhone and iPad. Hidden once the
    app is installed, and on browsers that cannot install it. The banner can
    be dismissed for 30 days. See resources/js/install.js.
--}}
<div x-data="installApp({ banner: @js($banner) })" x-show="available" x-cloak {{ $attributes }}>
    @if ($banner)
        <div class="flex items-center gap-3 rounded-3xl border-2 border-ink bg-white p-3 shadow-[0_3px_0_var(--color-ink)] sm:p-4">
            <img src="/icons/icon-192.png" alt="" width="48" height="48" class="size-12 shrink-0 rounded-xl">
            <div class="min-w-0 flex-1">
                <p class="font-display text-lg leading-tight font-bold">{{ __('Pasang app ASUH') }}</p>
                <p class="text-sm text-ink/70">{{ __('Buka terus dari skrin utama, macam app.') }}</p>
            </div>
            <button type="button" @click="install()" class="btn-utama shrink-0 px-4 text-sm">{{ __('Pasang') }}</button>
            <button type="button" @click="dismiss()" class="flex size-11 shrink-0 items-center justify-center rounded-full text-xl text-ink/60 hover:bg-ink/5" aria-label="{{ __('Tutup') }}">×</button>
        </div>
    @else
        <button type="button" @click="install()" class="btn-utama">📲 {{ __('Pasang app ASUH') }}</button>
    @endif

    {{-- iPhone and iPad: Safari has no install button, so show the steps. --}}
    <div x-show="showIosHelp" x-cloak @keydown.escape.window="showIosHelp = false"
        class="fixed inset-0 z-50 flex items-end justify-center bg-ink/60 p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="ios-help-title">
        <div @click.outside="showIosHelp = false" class="w-full max-w-sm rounded-3xl border-2 border-ink bg-white p-6 text-left shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="ios-help-title" class="font-display text-xl font-bold text-plum">{{ __('Pasang di iPhone atau iPad') }}</h2>
            <ol class="mt-4 space-y-3">
                <li class="flex gap-3"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-kuning font-extrabold">1</span> <span>{!! __('Buka laman ini dalam :safari.', ['safari' => '<strong>Safari</strong>']) !!}</span></li>
                <li class="flex gap-3"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-kuning font-extrabold">2</span> <span>{!! __('Tekan butang :share (petak dengan anak panah ke atas).', ['share' => '<strong>'.e(__('Kongsi')).'</strong>']) !!}</span></li>
                <li class="flex gap-3"><span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-kuning font-extrabold">3</span> <span>{!! __('Pilih :add, kemudian tekan :confirm.', ['add' => '<strong>'.e(__('Tambah ke Skrin Utama')).'</strong>', 'confirm' => '<strong>'.e(__('Tambah')).'</strong>']) !!}</span></li>
            </ol>
            <button type="button" @click="showIosHelp = false" class="btn-gelap mt-6 w-full">{{ __('Faham') }}</button>
        </div>
    </div>
</div>
