@php
    use App\Enums\AffiliateStatus;
    use App\Support\Money;

    $status = $affiliate?->status;
    $percentLabel = rtrim(rtrim(number_format($percent, 2), '0'), '.').'%';
@endphp

<x-layouts.account title="Affiliate" heading="Affiliate ASUH" :$activePass :$pendingPass :$shelfOpen :$plusUpgradePrice
    :intro="'Kongsi ASUH Digital dan dapatkan komisen '.$percentLabel.' bagi setiap jualan melalui pautan atau kod anda.'">

    @if (session('status') === 'affiliate-applied')
        <p role="status" class="rounded-2xl border-2 border-biru/30 bg-biru/5 px-4 py-3 font-semibold text-biru">Terima kasih. Permohonan anda telah dihantar dan akan disemak oleh pihak ASUH.</p>
    @elseif (session('status') === 'affiliate-saved')
        <p role="status" class="rounded-2xl border-2 border-biru/30 bg-biru/5 px-4 py-3 font-semibold text-biru">Maklumat affiliate disimpan.</p>
    @endif

    @if ($status === AffiliateStatus::Approved)
        {{-- Link and code --}}
        <section class="rounded-3xl border-2 border-ink bg-plum p-6 text-white shadow-[0_4px_0_var(--color-ink)]" x-data="{ copied: null, copy(text, which) { navigator.clipboard?.writeText(text); this.copied = which; setTimeout(() => this.copied = null, 2000) } }">
            <h2 class="font-display text-xl font-bold text-kuning">Pautan dan kod anda</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                <code class="block min-w-0 truncate rounded-xl bg-white/10 px-4 py-3 text-sm">{{ $affiliate->link() }}</code>
                <button type="button" class="btn-utama px-5 text-sm" @click="copy(@js($affiliate->link()), 'link')" x-text="copied === 'link' ? 'Disalin!' : 'Salin pautan'">Salin pautan</button>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <span class="text-sm text-white/80">Kod:</span>
                <code class="rounded-lg bg-white/10 px-3 py-1.5 font-display text-lg font-bold tracking-wide">{{ $affiliate->code }}</code>
                <button type="button" class="inline-flex min-h-11 items-center text-sm font-bold underline underline-offset-2" @click="copy(@js($affiliate->code), 'code')" x-text="copied === 'code' ? 'Disalin!' : 'Salin kod'">Salin kod</button>
            </div>
            <p class="mt-4 text-sm text-white/80">
                Pembeli yang membuka pautan anda dikira untuk anda selama {{ $cookieDays }} hari. Mereka juga boleh menaip kod anda di ruang "Kod promosi" semasa membeli.
                Komisen {{ $percentLabel }} daripada harga (selepas diskaun) boleh dibayar {{ $holdDays }} hari selepas bayaran pembeli, iaitu selepas tempoh bayaran balik.
            </p>
        </section>

        {{-- Totals --}}
        <dl class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ([
                ['Jualan', (string) $summary['orders'], 'Pesanan berbayar'],
                ['Dalam tempoh', Money::format($summary['pending']), $holdDays.' hari selepas bayaran'],
                ['Boleh dibayar', Money::format($summary['due']), 'Menunggu pindahan ASUH'],
                ['Telah dibayar', Money::format($summary['paid']), 'Jumlah keseluruhan'],
            ] as [$label, $value, $line])
                <div class="rounded-3xl border-2 border-ink/15 bg-white p-4">
                    <dt class="text-xs font-extrabold tracking-wide text-ink/70 uppercase">{{ $label }}</dt>
                    <dd class="mt-2 font-display text-2xl font-extrabold text-plum">{{ $value }}</dd>
                    <dd class="text-sm text-ink/70">{{ $line }}</dd>
                </div>
            @endforeach
        </dl>

        {{-- Commissions --}}
        <section aria-labelledby="commissions-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="commissions-heading" class="font-display text-xl font-bold text-plum">Komisen</h2>
            @if ($commissions->isEmpty())
                <p class="mt-2 text-ink/80">Belum ada jualan. Kongsi pautan anda di media sosial, kumpulan WhatsApp ibu bapa atau kelas anda.</p>
            @else
                <ul class="mt-4 divide-y-2 divide-ink/10">
                    @foreach ($commissions as $commission)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                {{-- No buyer names: the affiliate sees what sold and when. --}}
                                <p class="font-bold">{{ $commission->order?->itemName() }}</p>
                                <p class="text-sm text-ink/70">
                                    {{ $commission->created_at->translatedFormat('j M Y') }}
                                    @if ($commission->isDue()) · boleh dibayar
                                    @elseif ($commission->status === App\Enums\CommissionStatus::Pending) · boleh dibayar mulai {{ $commission->payable_at->translatedFormat('j M Y') }}
                                    @elseif ($commission->paid_at) · dibayar {{ $commission->paid_at->translatedFormat('j M Y') }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-bold">{{ Money::format($commission->amount_sen) }}</span>
                                <span class="rounded-full bg-ink/5 px-3 py-1 text-sm font-bold">{{ $commission->status->getLabel() }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @elseif ($status === AffiliateStatus::Pending)
        <section class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 class="font-display text-xl font-bold text-plum">Permohonan sedang disemak</h2>
            <p class="mt-2 text-ink/80">Kami akan memaklumkan anda melalui e-mel selepas permohonan disemak. Anda boleh mengemas kini maklumat di bawah sementara menunggu.</p>
        </section>
    @elseif ($status === AffiliateStatus::Rejected)
        <section class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 class="font-display text-xl font-bold text-plum">Permohonan belum diluluskan</h2>
            <p class="mt-2 text-ink/80">Permohonan anda sebelum ini tidak diluluskan. Anda boleh memohon semula dengan maklumat yang lebih lengkap.</p>
        </section>
    @elseif ($status === AffiliateStatus::Suspended)
        <section class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 class="font-display text-xl font-bold text-plum">Akaun affiliate digantung</h2>
            <p class="mt-2 text-ink/80">Pautan dan kod anda tidak aktif buat masa ini. Hubungi kami untuk maklumat lanjut.</p>
        </section>
    @else
        <section class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 class="font-display text-xl font-bold text-plum">Cara ia berfungsi</h2>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-ink/85">
                <li>Mohon di bawah. Pihak ASUH akan menyemak permohonan anda.</li>
                <li>Selepas diluluskan, anda mendapat pautan dan kod sendiri untuk dikongsi.</li>
                <li>Setiap jualan melalui pautan atau kod anda memberi komisen {{ $percentLabel }} daripada harga.</li>
                <li>Komisen dibayar ke akaun bank anda {{ $holdDays }} hari selepas bayaran pembeli.</li>
            </ol>
        </section>
    @endif

    @if ($status !== AffiliateStatus::Suspended)
        <section aria-labelledby="affiliate-form-heading" class="rounded-3xl border-2 border-ink bg-white p-6 shadow-[0_4px_0_var(--color-ink)]">
            <h2 id="affiliate-form-heading" class="font-display text-xl font-bold text-plum">
                {{ $affiliate && $status !== AffiliateStatus::Rejected ? 'Maklumat affiliate' : 'Mohon menjadi affiliate' }}
            </h2>
            <form method="POST" action="{{ route('account.affiliate.store') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="promotion_channels" class="label">Bagaimana anda akan mempromosi ASUH?</label>
                    <textarea id="promotion_channels" name="promotion_channels" rows="3" class="input" required maxlength="1000"
                        placeholder="Contoh: Instagram @nama (5,000 pengikut), kumpulan WhatsApp ibu bapa tadika">{{ old('promotion_channels', $affiliate?->promotion_channels) }}</textarea>
                    @error('promotion_channels') <p class="mt-1 text-sm font-semibold text-magenta">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field name="bank_name" label="Nama bank" :value="$affiliate?->bank_name" required />
                    <x-field name="bank_account_name" label="Nama pemegang akaun" :value="$affiliate?->bank_account_name" required />
                    <x-field name="bank_account_number" label="Nombor akaun" :value="$affiliate?->bank_account_number" inputmode="numeric" required />
                </div>
                <p class="text-sm text-ink/70">Komisen hanya dibayar ke akaun bank ini. Pembelian anda sendiri tidak dikira.</p>
                <button type="submit" class="btn-gelap">{{ $affiliate && $status !== AffiliateStatus::Rejected ? 'Simpan' : 'Hantar permohonan' }}</button>
            </form>
        </section>
    @endif
</x-layouts.account>
