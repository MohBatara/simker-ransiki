{{--
    Tab Profil detail kelompok (Admin/OPD) dan halaman Profil Kelompok. Desktop: kolom utama 7/12 dan
    kolom samping 5/12; HP: Ringkasan lebih dulu, lalu kartu lain berurutan.
    Variabel: $organization, $overview (App\Support\OrganizationOverview::data), $area, $documents.
--}}
@use('App\Support\Format')

@php
    $text = fn ($value) => filled($value) ? $value : '—';
    $latestReport = $overview['latestReport'];
    $productsUrl = $area === 'kelompok' ? route('kelompok.products.index') : route($area.'.organizations.products', $organization);
    $reportUrl = match (true) {
        ! $latestReport => null,
        $area === 'kelompok' => route('kelompok.reports.show', $latestReport),
        (bool) $latestReport->submitted_at => route($area.'.reports.show', [$latestReport, 'dari' => 'kelompok']),
        default => null,
    };
    $legal = $organization->legal_status->label().($organization->legal_number ? ' · '.$organization->legal_number : '');
    $info = [
        'Nama kelompok' => $organization->name,
        'Jenis kelembagaan' => $organization->institutionType->name,
        'Status' => $organization->status->label(),
        'Tahun berdiri' => $text($organization->founded_year),
        'Ketua' => $text($organization->leader_name),
        'Telepon' => $text($organization->phone),
        'Email' => $text($organization->email),
        'Bidang usaha' => $text($organization->sectors->pluck('name')->join(', ')),
        'Jumlah anggota' => $organization->members_count === null ? '—' : Format::number($organization->members_count).' orang',
        'Tenaga kerja' => $organization->workforce_count === null ? '—' : Format::number($organization->workforce_count).' orang',
        'Kapasitas produksi' => $text($organization->production_capacity),
        'Pasar' => $text($organization->markets),
        'Aset' => $text($organization->assets),
        'Fasilitas' => $text($organization->facilities),
        'Peralatan' => $text($organization->equipment),
        'Legalitas' => $legal,
        'Catatan legalitas' => $text($organization->legal_notes),
    ];
@endphp

<div class="grid gap-5 lg:grid-cols-12 lg:items-start">
    {{-- Ringkasan: paling atas di HP, kolom samping di desktop. --}}
    <x-admin.card title="Ringkasan" icon="chart-bar-square" class="max-lg:order-first lg:col-span-5 lg:col-start-8 lg:row-start-2">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-info-item label="Jumlah anggota" icon="user-group">
                {{ $organization->members_count === null ? '—' : Format::number($organization->members_count).' orang' }}
            </x-info-item>
            <x-info-item label="Produk" icon="cube">
                <x-link :href="$productsUrl" class="font-normal tabular-nums">{{ $organization->products_count }} produk</x-link>
            </x-info-item>
            <x-info-item label="Laporan terakhir" icon="document-text">
                @if ($latestReport)
                    <span class="flex flex-wrap items-center gap-2">
                        @if ($reportUrl)
                            <x-link :href="$reportUrl" class="font-normal">{{ Format::month($latestReport->period) }}</x-link>
                        @else
                            {{ Format::month($latestReport->period) }}
                        @endif
                        <x-admin.badge-status :status="$latestReport->status" :label="$area === 'kelompok' ? $latestReport->status->label() : $latestReport->status->reviewerLabel()" />
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

    <x-admin.card title="Informasi umum" icon="identification" class="lg:col-span-7 lg:row-span-3 lg:row-start-1">
        <dl class="divide-y divide-line">
            @foreach ($info as $label => $value)
                <div class="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,11rem)_1fr] sm:gap-4">
                    <dt class="text-ink-soft">{{ $label }}</dt>
                    <dd class="whitespace-pre-line break-words text-ink">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-admin.card>

    <x-admin.card title="Lokasi" icon="map-pin" class="lg:col-span-5 lg:col-start-8 lg:row-start-1">
        <dl class="space-y-3">
            <div>
                <dt class="text-caption text-ink-muted">Kampung</dt>
                <dd class="text-ink">{{ $organization->village->name }}</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">Distrik</dt>
                <dd class="text-ink">Ransiki</dd>
            </div>
            <div>
                <dt class="text-caption text-ink-muted">Alamat</dt>
                <dd class="whitespace-pre-line break-words text-ink">{{ $text($organization->address) }}</dd>
            </div>
        </dl>
        <x-button variant="secondary" icon="arrow-top-right-on-square" :href="$overview['mapsUrl']" target="_blank" rel="noopener" class="mt-4 max-sm:w-full">Buka di Google Maps</x-button>
        <p class="mt-2 text-caption text-ink-muted">Dicari dari alamat; titik lokasi kelompok belum tercatat.</p>
    </x-admin.card>

    <x-admin.card title="Deskripsi" icon="document-text" class="lg:col-span-5 lg:col-start-8 lg:row-start-3">
        @if (filled($organization->description))
            <p class="whitespace-pre-line break-words text-ink">{{ $organization->description }}</p>
        @else
            <p class="text-ink-soft">Belum ada deskripsi kegiatan kelompok.</p>
        @endif
    </x-admin.card>

    <x-admin.card title="Foto" icon="photo" class="lg:col-span-7">
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

    <x-admin.card title="Publikasi & pelaporan" icon="globe-alt" class="lg:col-span-5">
        <dl class="space-y-3">
            <div>
                <dt class="text-caption text-ink-muted">Verifikasi profil</dt>
                <dd class="mt-0.5 space-y-1.5">
                    <x-admin.badge-status :status="$organization->profile_status" />
                    @if ($organization->profile_verified_at)
                        <p class="text-caption text-ink-soft">
                            Diverifikasi {{ Format::date($organization->profile_verified_at) }}@if ($organization->profileVerifier) oleh {{ $organization->profileVerifier->nameWithAgency() }}@endif
                        </p>
                    @endif
                    @if ($organization->profile_status === App\Enums\ProfileStatus::NeedsReview)
                        <p class="text-caption text-ink-soft">Diubah kelompok: {{ implode(', ', App\Support\ProfileReview::changedLabels($organization)) }}</p>
                    @endif
                </dd>
                @if ($area !== 'kelompok' && $organization->profile_status !== App\Enums\ProfileStatus::Verified)
                    @can('verifyProfile', $organization)
                        <form method="POST" action="{{ route($area.'.organizations.verify-profile', $organization) }}" class="mt-3">
                            @csrf
                            <x-button type="submit" variant="secondary" icon="check-badge" processing="Menyimpan…">Tandai profil terverifikasi</x-button>
                        </form>
                    @endcan
                @endif
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
                <dd class="text-ink">{{ Format::month($organization->reporting_starts_on) }}</dd>
            </div>
            @if ($overview['statusChanges']->isNotEmpty())
                <div>
                    <dt class="text-caption text-ink-muted">Riwayat status</dt>
                    <dd>
                        <ol class="mt-1 space-y-2">
                            @foreach ($overview['statusChanges'] as $change)
                                <li>
                                    <p class="text-ink">{{ $change->from_status->label() }} → {{ $change->to_status->label() }}</p>
                                    <p class="text-caption text-ink-soft">{{ Format::date($change->created_at) }}@if ($change->user) · {{ $change->user->name }}@endif</p>
                                    <p class="text-caption text-ink-soft">{{ $change->reason }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </dd>
                </div>
            @endif
            <div>
                <dt class="text-caption text-ink-muted">Terakhir diubah</dt>
                <dd class="text-ink">{{ Format::date($organization->updated_at) }}@if ($organization->updater) oleh {{ $organization->updater->name }}@endif</dd>
            </div>
        </dl>
    </x-admin.card>

    <div class="lg:col-span-12">
        @include('shared.attachments', $documents + ['icon' => 'paper-clip'])
    </div>
</div>
