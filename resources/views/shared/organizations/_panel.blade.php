{{--
    Isi popup pratinjau kelompok (x-admin.modal lg di daftar Kelompok, Verifikasi profil, dan Kemungkinan data ganda).
    Dirender bersama halaman untuk ?pratinjau=ID, atau diambil lewat /{area}/kelompok/{id}/ringkas (Policy view) saat baris
    diklik. Semua gambar dimuat langsung (tanpa lazy) karena popup awalnya tersembunyi.
    Variabel: $panel (App\Support\OrganizationPanel::data), $area.
--}}
@use('App\Support\Format')

@php
    $organization = $panel['organization'];
    $text = fn ($value) => filled($value) ? $value : Format::orEmpty(null);
    $numbers = [
        ['Anggota', $organization->members_count === null ? null : Format::number($organization->members_count)],
        ['Produk', Format::number($organization->products_count)],
        ['Izin terbit', Format::number($organization->issued_licenses_count)],
        ['Laporan terverifikasi', Format::number($organization->verified_reports_count)],
    ];
@endphp

{{-- Tujuan tombol kaki popup ("Ubah profil", "Buka detail") dibaca dari atribut ini setelah isi dimuat. --}}
<section class="space-y-5" aria-labelledby="panel-judul" data-panel-id="{{ $organization->id }}"
         data-detail-url="{{ route($area.'.organizations.show', $organization) }}"
         @can('update', $organization) data-edit-url="{{ route($area.'.organizations.edit', $organization) }}" @endcan>
    {{-- Foto utama 16:9 (maks 240px); tanpa foto: kotak brand-50 berinisial. --}}
    @if ($panel['photo'])
        <img src="{{ $panel['photo']['url'] }}" alt="{{ $panel['photo']['alt'] }}" decoding="async" data-organization-photo
             class="aspect-video max-h-[240px] w-full rounded-[10px] bg-subtle object-cover">
    @else
        <div class="flex aspect-video max-h-[240px] w-full flex-col items-center justify-center gap-1 rounded-[10px] bg-brand-50" data-organization-photo-empty>
            <span class="text-display font-semibold text-brand" aria-hidden="true">{{ App\Support\Initials::of($organization->name) }}</span>
            <span class="text-label text-ink-2">Belum ada foto</span>
        </div>
    @endif

    <div>
        <h2 id="panel-judul" tabindex="-1" class="text-page-title font-semibold text-ink focus:outline-none">{{ $organization->name }}</h2>
        <p class="mt-0.5 text-caption text-ink-2">{{ $organization->institutionType->name }} <span aria-hidden="true">·</span> {{ $organization->village->name }}</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <x-admin.badge-status :status="$organization->status" />
            <x-admin.badge-status :status="$organization->profile_status" />
        </div>
    </div>

    {{-- Ringkasan angka. --}}
    <dl class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        @foreach ($numbers as [$label, $value])
            <div class="rounded-control border border-line px-3 py-2">
                <dt class="text-label text-ink-2">{{ $label }}</dt>
                <dd class="mt-0.5 text-card-head font-semibold tabular-nums text-ink">{{ Format::orEmpty($value) }}</dd>
            </div>
        @endforeach
    </dl>

    {{-- Ketua, kontak, dan data inti. --}}
    <div class="grid gap-4 sm:grid-cols-2">
        <x-info-item label="Ketua" icon="user">{{ $text($organization->leader_name) }}</x-info-item>
        <x-info-item label="Telepon" icon="phone">
            @if (filled($organization->phone))
                <x-link :href="'tel:'.preg_replace('/[^\d+]/', '', $organization->phone)" class="font-normal">{{ $organization->phone }}</x-link>
            @else
                {{ Format::orEmpty(null) }}
            @endif
        </x-info-item>
        <x-info-item label="Email" icon="envelope">{{ $text($organization->email) }}</x-info-item>
        <x-info-item label="Tahun berdiri" icon="calendar">{{ $text($organization->founded_year) }}</x-info-item>
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

    <section class="border-t border-line-soft pt-4">
        <div class="mb-2 flex items-center justify-between gap-3">
            <h3 class="text-caption font-semibold text-ink">Produk ({{ $organization->products_count }})</h3>
            <x-link :href="route($area.'.organizations.products', $organization)" class="text-caption">Semua produk</x-link>
        </div>
        @if ($panel['products']->isEmpty())
            <p class="text-caption text-ink-2">Belum ada produk.</p>
        @else
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($panel['products'] as $product)
                    <li class="min-w-0">
                        <div class="aspect-[4/3] overflow-hidden rounded-control bg-subtle">
                            @if ($product->photo_path)
                                <img src="{{ $product->photoUrl(thumbnail: true) }}" alt="" decoding="async" class="size-full object-cover">
                            @else
                                <span class="flex size-full items-center justify-center font-semibold text-ink-muted" aria-hidden="true">{{ $product->initials() }}</span>
                            @endif
                        </div>
                        <p class="mt-1 truncate text-caption text-ink">{{ $product->name }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="border-t border-line-soft pt-4">
        <div class="mb-1 flex items-center justify-between gap-3">
            <h3 class="text-caption font-semibold text-ink">Laporan terakhir</h3>
            <x-link :href="route($area.'.organizations.reports', $organization)" class="text-caption">Semua laporan</x-link>
        </div>
        @if ($panel['reports']->isEmpty())
            <p class="text-caption text-ink-2">Belum ada laporan.</p>
        @else
            <x-list>
                @foreach ($panel['reports'] as $report)
                    <x-list.item :href="auth()->user()->can('view', $report) ? route($area.'.reports.show', [$report, 'dari' => 'kelompok']) : null">
                        <span class="min-w-0 flex-1 text-ink">{{ Format::month($report->period) }}</span>
                        <x-admin.badge-status :status="$report->status" />
                    </x-list.item>
                @endforeach
            </x-list>
        @endif
    </section>
</section>
