{{--
    Kartu "Ekonomi <bulan>" dasbor Admin, tab Ringkasan. Angka hanya dari laporan terverifikasi; NULL tidak dihitung 0.
    Cakupan (terverifikasi dari wajib lapor) + bar progres, lalu 4 kotak angka (rupiah ringkas, nilai lengkap di tooltip),
    kotak Laba forest (pengecualian token forest/sun atas permintaan, dijaga PublicStyleTest).
    Variabel: $economy (DistrictStats::economy()), $period, $verifiedUrl, $class (opsional).
--}}
@use('App\Support\Format')

@php
    $reports = $economy['reports'];
    $obligated = $economy['obligated'];
    $percent = $obligated > 0 ? (int) round($reports / $obligated * 100) : 0;
    $boxes = [
        'income_sales' => 'Pendapatan penjualan',
        'income_other' => 'Pendapatan lain',
        'costs' => 'Biaya',
        'profit' => 'Laba',
    ];
    $notes = [
        'Hanya dari laporan terverifikasi.',
        'Angka kosong tidak dihitung sebagai nol.',
        'Laba hanya dari laporan yang mengisi penjualan, pendapatan lain, dan biaya.',
    ];
@endphp

<x-admin.card :title="'Ekonomi '.Format::month($period)" description="Dari laporan terverifikasi" stretch :class="$class ?? null">
    <x-slot:actions>
        {{-- Catatan cara menghitung sebagai tooltip (hover/fokus), bukan di badan kartu. --}}
        <span class="group relative inline-flex">
            <button type="button" class="flex size-11 items-center justify-center rounded-full text-ink-muted transition-colors hover:bg-subtle hover:text-ink md:size-8" aria-label="Cara menghitung" aria-describedby="catatan-ekonomi">
                <x-heroicon-o-information-circle class="size-5" />
            </button>
            <span id="catatan-ekonomi" role="tooltip" class="invisible absolute right-0 top-full z-20 mt-1 w-72 rounded-card bg-ink px-3 py-2 text-caption text-paper opacity-0 shadow-float transition-opacity group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                @foreach ($notes as $note)
                    <span class="block">{{ $note }}</span>
                @endforeach
            </span>
        </span>
    </x-slot:actions>

    @if ($reports === 0)
        <div class="flex flex-1 flex-col items-center justify-center py-8 text-center">
            <span class="flex size-12 items-center justify-center rounded-full bg-subtle text-ink-soft" aria-hidden="true">
                <x-heroicon-o-banknotes class="size-6" />
            </span>
            <p class="mt-4 max-w-sm text-ui text-ink">Belum ada laporan terverifikasi untuk {{ Format::month($period) }}.</p>
            <x-button variant="secondary" class="mt-4" :href="route('admin.verifications.index')">Buka laporan yang menunggu verifikasi</x-button>
        </div>
    @else
        <div class="space-y-6">
            {{-- Cakupan: berapa kelompok wajib lapor yang laporannya sudah terverifikasi. --}}
            <div class="space-y-2">
                <div class="flex items-baseline justify-between gap-3 text-ui">
                    <p class="text-ink-soft">
                        <a href="{{ $verifiedUrl }}" class="font-semibold tabular-nums text-ink hover:underline">{{ Format::number($reports) }} dari {{ Format::number($obligated) }}</a>
                        kelompok wajib lapor terverifikasi
                    </p>
                    <span class="shrink-0 font-semibold tabular-nums text-ink">{{ $percent }}%</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-subtle" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}" aria-label="Cakupan laporan terverifikasi">
                    <div class="h-full rounded-full bg-brand" style="width: {{ min(100, $percent) }}%"></div>
                </div>
                @if ($percent < 50)
                    <p class="text-caption text-clay">Angka belum mewakili seluruh kelompok.</p>
                @endif
            </div>

            {{-- 4 angka utama 2x2 (juga di HP). Singkatan rb/jt/M hanya di sini; nilai lengkap di tooltip. --}}
            <dl class="grid grid-cols-2 gap-2 md:gap-3">
                @foreach ($boxes as $field => $label)
                    @php
                        $total = $economy['totals'][$field];
                        $profit = $field === 'profit';
                        $loss = $total['sum'] !== null && $total['sum'] < 0;
                        $short = $total['sum'] === null ? null : ($loss ? 'Rugi ' : '').Format::rupiahShort($total['sum']);
                        $full = $total['sum'] === null ? null : ($loss ? 'Rugi Rp '.Format::number(abs($total['sum'])) : Format::rupiah($total['sum']));
                    @endphp
                    <div @class(['flex min-w-0 flex-col gap-1 rounded-[12px] p-3 md:p-4', 'bg-forest' => $profit, 'bg-canvas' => ! $profit])>
                        <dt @class(['text-caption', 'text-sun' => $profit, 'text-ink-soft' => ! $profit])>{{ $label }}</dt>
                        <dd class="min-w-0">
                            @if ($total['sum'] === null)
                                <span @class(['block text-ui', 'text-cream' => $profit, 'text-ink-soft' => ! $profit])>Belum ada yang mengisi</span>
                            @else
                                <a href="{{ $verifiedUrl }}" title="{{ $full }}" @class([
                                    'block text-[1.25rem] leading-7 font-semibold whitespace-nowrap tabular-nums hover:underline md:text-[1.375rem] md:leading-[1.875rem]',
                                    'text-cream' => $profit && ! $loss,
                                    'text-sand' => $profit && $loss,
                                    'text-clay' => ! $profit && $loss,
                                    'text-ink' => ! $profit && ! $loss,
                                ])>{{ $short }}<span class="sr-only"> ({{ $full }})</span></a>
                                @if ($total['filled'] < $reports)
                                    <span @class(['block text-caption', 'text-cream' => $profit, 'text-ink-muted' => ! $profit])>diisi {{ $total['filled'] }} dari {{ $reports }} laporan</span>
                                @endif
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</x-admin.card>
