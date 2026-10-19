{{--
    Isi popup pratinjau kelompok di daftar kelompok (desktop ≥ 1280px). Dirender bersama halaman untuk kelompok
    terpilih, atau diambil lewat /{area}/kelompok/{id}/ringkas saat baris diklik.
    Variabel: $panel (App\Support\OrganizationPanel::data), $area.
--}}
@use('App\Support\Format')

@php
    $organization = $panel['organization'];
    $text = fn ($value) => filled($value) ? $value : \App\Support\Format::orEmpty(null);
@endphp

<section class="bg-panel" aria-labelledby="panel-judul" data-panel-id="{{ $organization->id }}">
    <div class="relative aspect-[3/1] overflow-hidden rounded-control bg-subtle">
        @if ($panel['image'])
            <x-public.picture :image="$panel['image']" alt="" sizes="400px" width="800" height="400" frame="block size-full" class="size-full object-cover" />
        @else
            <span class="flex size-full items-center justify-center text-page-title font-semibold text-ink-muted" aria-hidden="true">{{ App\Support\Initials::of($organization->name) }}</span>
        @endif
    </div>

    <div class="space-y-4 p-5">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 id="panel-judul" tabindex="-1" class="text-page-title font-semibold text-ink focus:outline-none">{{ $organization->name }}</h2>
                <x-admin.badge-status :status="$organization->status" />
            </div>
            <p class="text-ui text-ink-soft">{{ $organization->village->name }}, Distrik Ransiki</p>
        </div>

        <x-tabs :tabs="['informasi' => 'Informasi', 'produk' => 'Produk ('.$organization->products_count.')', 'laporan' => 'Laporan']">
            <x-tabs.panel name="informasi">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-info-item label="Ketua" icon="user">{{ $text($organization->leader_name) }}</x-info-item>
                    <x-info-item label="Tahun berdiri" icon="calendar">{{ $text($organization->founded_year) }}</x-info-item>
                    <x-info-item label="Jumlah anggota" icon="user-group">{{ $organization->members_count === null ? \App\Support\Format::orEmpty(null) : Format::number($organization->members_count).' orang' }}</x-info-item>
                    <x-info-item label="Jenis" icon="building-office">{{ $organization->institutionType->name }}</x-info-item>
                    <x-info-item label="Bidang usaha" icon="squares-2x2" class="sm:col-span-2">{{ $text($organization->sectors->pluck('name')->join(', ')) }}</x-info-item>
                    <x-info-item label="OPD pembina" icon="briefcase" class="sm:col-span-2">
                        @if ($organization->opds->isEmpty())
                            <x-admin.badge-status tone="warning" label="Belum ada OPD pembina" />
                        @else
                            {{ $organization->opds->map(fn ($opd) => $opd->short_name ?: $opd->name)->join(', ') }}
                        @endif
                    </x-info-item>
                    <x-info-item label="Alamat" icon="map-pin" class="sm:col-span-2">{{ $text($organization->address) }}</x-info-item>
                </div>
            </x-tabs.panel>

            <x-tabs.panel name="produk">
                @if ($panel['products']->isEmpty())
                    <p class="text-ink-soft">Belum ada produk.</p>
                @else
                    <ul class="grid grid-cols-2 gap-3">
                        @foreach ($panel['products'] as $product)
                            <li class="min-w-0">
                                <div class="aspect-[4/3] overflow-hidden rounded-control bg-subtle">
                                    @if ($product->photo_path)
                                        <img src="{{ $product->photoUrl(thumbnail: true) }}" alt="" loading="lazy" decoding="async" class="size-full object-cover">
                                    @else
                                        <span class="flex size-full items-center justify-center font-semibold text-ink-muted" aria-hidden="true">{{ $product->initials() }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 truncate text-ui text-ink">{{ $product->name }}</p>
                                @if ($product->unit)
                                    <p class="text-caption text-ink-muted">per {{ $product->unit }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <x-link :href="route($area.'.organizations.products', $organization)" class="mt-2">Semua produk</x-link>
            </x-tabs.panel>

            <x-tabs.panel name="laporan">
                @if ($panel['reports']->isEmpty())
                    <p class="text-ink-soft">Belum ada laporan.</p>
                @else
                    <x-list>
                        @foreach ($panel['reports'] as $report)
                            <x-list.item :href="auth()->user()->can('view', $report) ? route($area.'.reports.show', [$report, 'dari' => 'kelompok']) : null">
                                <span class="min-w-0 flex-1 text-ink">{{ \App\Support\Format::orEmpty(Format::month($report->period)) }}</span>
                                <x-admin.badge-status :status="$report->status" :label="$report->status->label()" />
                            </x-list.item>
                        @endforeach
                    </x-list>
                @endif
                <x-link :href="route($area.'.organizations.reports', $organization)" class="mt-2">Semua laporan</x-link>
            </x-tabs.panel>
        </x-tabs>

        <div class="flex flex-wrap gap-2 border-t border-line pt-4">
            @can('update', $organization)
                <x-button variant="secondary" icon="pencil-square" :href="route($area.'.organizations.edit', $organization)">Ubah</x-button>
            @endcan
            <x-button :href="route($area.'.organizations.show', $organization)" class="flex-1">Buka detail</x-button>
        </div>
    </div>
</section>
