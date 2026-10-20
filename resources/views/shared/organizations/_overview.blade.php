{{--
    Tab Profil detail kelompok (Admin/OPD) dan halaman Profil kelompok, tanpa mengulang isi kepala halaman. Desktop: kolom
    kiri 8/12 (Informasi umum, Usaha dan sumber daya, Deskripsi, Foto) dan kanan 4/12 (Lokasi, Ringkasan, Publikasi);
    HP bertumpuk. Data kosong: "Belum diisi" (Format::orEmpty).
    Variabel: $organization, $overview (App\Support\OrganizationOverview::data), $area, $documents.
--}}
@use('App\Support\Format')

@php
    $text = fn ($value) => filled($value) ? $value : \App\Support\Format::orEmpty(null);
    $latestReport = $overview['latestReport'];
    $productsUrl = $area === 'kelompok' ? route('kelompok.products.index') : route($area.'.organizations.products', $organization);
    $reportUrl = match (true) {
        ! $latestReport => null,
        $area === 'kelompok' => route('kelompok.reports.show', $latestReport),
        (bool) $latestReport->submitted_at => route($area.'.reports.show', [$latestReport, 'dari' => 'kelompok']),
        default => null,
    };
    $legal = $organization->legal_status->label().($organization->legal_number ? ' · '.$organization->legal_number : '');
    // Nama, jenis, status, kampung, distrik, dan bidang usaha sudah ada di kepala halaman.
    $info = [
        'Ketua' => $text($organization->leader_name),
        'Telepon' => $text(\App\Support\Format::phone($organization->phone)),
        'Email' => $text($organization->email),
        'Tahun berdiri' => $text($organization->founded_year),
        'Jumlah anggota' => $organization->members_count === null ? null : Format::number($organization->members_count).' orang',
    ];
    $business = [
        'Tenaga kerja' => $organization->workforce_count === null ? null : Format::number($organization->workforce_count).' orang',
        'Kapasitas produksi' => $organization->production_capacity,
        'Pasar' => $organization->markets,
        'Aset' => $organization->assets,
        'Fasilitas' => $organization->facilities,
        'Peralatan' => $organization->equipment,
        'Legalitas' => $legal,
        'Catatan legalitas' => $organization->legal_notes,
    ];
@endphp

{{-- Kiri 8 kolom (informasi, usaha, deskripsi, foto), kanan 4 kolom (lokasi, ringkasan, publikasi); jarak 20px. --}}
<div class="grid gap-5 lg:grid-cols-12 lg:items-start">
    <div class="space-y-5 lg:col-span-8">
    <x-admin.card title="Informasi umum" icon="identification">
        <dl class="divide-y divide-line-soft">
            @foreach ($info as $label => $value)
                <div class="grid gap-1 py-2.5 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,10rem)_1fr] sm:gap-4">
                    <dt class="text-caption text-ink-2">{{ $label }}</dt>
                    <dd class="whitespace-pre-line break-words text-ink">{{ \App\Support\Format::orEmpty($value) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-admin.card>

    <x-admin.card title="Usaha dan sumber daya" icon="briefcase">
        <dl class="divide-y divide-line-soft">
            @foreach ($business as $label => $value)
                <div class="grid gap-1 py-2.5 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,10rem)_1fr] sm:gap-4">
                    <dt class="text-caption text-ink-2">{{ $label }}</dt>
                    <dd class="whitespace-pre-line break-words text-ink">{{ \App\Support\Format::orEmpty($value) }}</dd>
                </div>
            @endforeach
        </dl>
    </x-admin.card>

    <x-admin.card title="Deskripsi" icon="document-text">
        @if (filled($organization->description))
            <p class="whitespace-pre-line break-words text-ink">{{ $organization->description }}</p>
        @else
            <p class="text-ink-soft">Belum ada deskripsi kegiatan kelompok.</p>
        @endif
    </x-admin.card>

    <x-admin.card title="Foto" icon="photo">
        @if ($overview['photos']->isNotEmpty())
            <x-slot:actions>
                <x-link :href="$productsUrl">Lihat semua</x-link>
            </x-slot:actions>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($overview['photos'] as $photo)
                    <li class="aspect-[4/3] overflow-hidden rounded-control bg-subtle">
                        <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy" decoding="async" class="size-full object-cover">
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-ink-soft">Belum ada foto. Foto produk dan lampiran gambar kelompok akan tampil di sini.</p>
        @endif
    </x-admin.card>
    </div>

    <div class="space-y-5 lg:col-span-4">
    <x-admin.card title="Lokasi" icon="map-pin">
        <dl>
            <dt class="text-caption text-ink-2">Alamat</dt>
            <dd class="mt-0.5 whitespace-pre-line break-words text-ink">{{ $text($organization->address) }}</dd>
        </dl>
        <x-button variant="secondary" icon="arrow-top-right-on-square" :href="$overview['mapsUrl']" target="_blank" rel="noopener" class="mt-4 max-sm:w-full">Buka di Google Maps</x-button>
        <p class="mt-2 text-label text-ink-3">Dicari dari alamat; titik lokasi kelompok belum tercatat.</p>
    </x-admin.card>

    <x-admin.card title="Ringkasan" icon="chart-bar-square">
        <div class="grid gap-4">
            <x-info-item label="Laporan terakhir" icon="document-text">
                @if ($latestReport)
                    <span class="flex flex-wrap items-center gap-2">
                        @if ($reportUrl)
                            <x-link :href="$reportUrl" class="font-normal">{{ \App\Support\Format::orEmpty(Format::month($latestReport->period)) }}</x-link>
                        @else
                            {{ \App\Support\Format::orEmpty(Format::month($latestReport->period)) }}
                        @endif
                        <x-admin.badge-status :status="$latestReport->status"  />
                    </span>
                @else
                    Belum ada laporan
                @endif
            </x-info-item>
            <x-info-item label="OPD pembina" icon="briefcase">
                @if ($organization->opds->isEmpty())
                    <x-admin.badge-status tone="warning" label="Belum ada OPD pembina" />
                @else
                    {{ $organization->opds->map(fn ($opd) => $opd->short_name ?: $opd->name)->join(', ') }}
                @endif
            </x-info-item>
        </div>
    </x-admin.card>

    <x-admin.card title="Publikasi & pelaporan" icon="globe-alt">
        <dl class="space-y-3">
            <div>
                <dt class="text-caption text-ink-muted">Verifikasi profil</dt>
                <dd class="mt-0.5 space-y-1.5">
                    <x-admin.badge-status :status="$organization->profile_status" />
                    @if ($organization->profile_verified_at)
                        <p class="text-caption text-ink-soft">
                            Diverifikasi {{ \App\Support\Format::orEmpty(Format::date($organization->profile_verified_at)) }}@if ($organization->profileVerifier) oleh {{ $organization->profileVerifier->nameWithAgency() }}@endif
                        </p>
                    @endif
                    @if ($organization->profile_status === App\Enums\ProfileStatus::NeedsReview)
                        <p class="text-caption text-ink-soft">Diubah kelompok: {{ implode(', ', App\Support\ProfileReview::changedLabels($organization)) }}</p>
                    @endif
                </dd>
                {{-- "Tandai terverifikasi" ada di kepala halaman (x-admin.organization-header). --}}
            </div>
            <div>
                <dt class="text-caption text-ink-muted">Tampil di direktori publik</dt>
                <dd class="text-ink">{{ $organization->is_public ? 'Ya' : 'Tidak' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">Kontak tampil di halaman publik</dt>
                <dd class="text-ink">{{ $organization->show_contact_publicly ? 'Ya' : 'Tidak' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">Mulai wajib lapor</dt>
                <dd class="text-ink">{{ \App\Support\Format::orEmpty(Format::month($organization->reporting_starts_on)) }}</dd>
            </div>
            @if ($overview['statusChanges']->isNotEmpty())
                <div>
                    <dt class="text-caption text-ink-muted">Riwayat status</dt>
                    <dd>
                        <ol class="mt-1 space-y-2">
                            @foreach ($overview['statusChanges'] as $change)
                                <li>
                                    <p class="text-ink">{{ $change->from_status->label() }} → {{ $change->to_status->label() }}</p>
                                    <p class="text-caption text-ink-soft">{{ \App\Support\Format::orEmpty(Format::date($change->created_at)) }}@if ($change->user) · {{ $change->user->name }}@endif</p>
                                    <p class="text-caption text-ink-soft">{{ $change->reason }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </dd>
                </div>
            @endif
            <div>
                <dt class="text-caption text-ink-muted">Terakhir diubah</dt>
                <dd class="text-ink">{{ \App\Support\Format::orEmpty(Format::date($organization->updated_at)) }}@if ($organization->updater) oleh {{ $organization->updater->name }}@endif</dd>
            </div>
        </dl>
    </x-admin.card>
    </div>

    <div class="lg:col-span-12">
        @include('shared.attachments', $documents + ['icon' => 'paper-clip'])
    </div>
</div>
