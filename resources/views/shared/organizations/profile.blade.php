{{-- Profil kelompok baca-saja. Butuh relasi: institutionType, village, sectors, opds, updater. --}}
@use('App\Support\Format')

@php
    $text = fn ($value) => filled($value) ? $value : '—';
    $sections = [
        'Identitas' => [
            'Ketua' => $text($organization->leader_name),
            'Telepon' => $text($organization->phone),
            'Email' => $text($organization->email),
            'Tahun berdiri' => $text($organization->founded_year),
            'Jumlah anggota' => Format::number($organization->members_count),
            'Alamat' => $text($organization->address),
            'Deskripsi' => $text($organization->description),
        ],
        'Bidang Usaha & OPD Terkait' => [
            'Bidang usaha' => $text($organization->sectors->pluck('name')->join(', ')),
            'OPD terkait' => $organization->opds->pluck('name')->join(', ') ?: 'Belum ada OPD pembina',
        ],
        'Informasi Usaha' => [
            'Kapasitas produksi' => $text($organization->production_capacity),
            'Pasar' => $text($organization->markets),
            'Tenaga kerja' => Format::number($organization->workforce_count),
            'Aset' => $text($organization->assets),
            'Fasilitas' => $text($organization->facilities),
            'Peralatan' => $text($organization->equipment),
        ],
        'Legalitas' => [
            'Status hukum' => $organization->legal_status->label(),
            'Nomor legalitas' => $text($organization->legal_number),
            'Catatan' => $text($organization->legal_notes),
        ],
        'Publikasi' => [
            'Tampil di direktori publik' => $organization->is_public ? 'Ya' : 'Tidak',
            'Kontak tampil di halaman publik' => $organization->show_contact_publicly ? 'Ya' : 'Tidak',
        ],
        'Pelaporan' => [
            'Status kelompok' => $organization->status->label(),
            'Mulai wajib lapor' => Format::month($organization->reporting_starts_on),
        ],
    ];
@endphp

{{--
    Layar lebar: kartu utama (Identitas, Informasi Usaha, dokumen) di kiri, kartu ringkas di kolom samping.
    $documents (opsional): data untuk shared.attachments, ditampilkan di kolom utama.
--}}
@php
    $side = ['Bidang Usaha & OPD Terkait', 'Legalitas', 'Publikasi', 'Pelaporan'];
@endphp

<div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
    <div class="min-w-0 space-y-5">
        @foreach (collect($sections)->except($side) as $title => $rows)
            <x-card :title="$title">
                <dl class="divide-y divide-line">
                    @foreach ($rows as $label => $value)
                        <div class="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,12rem)_1fr] sm:gap-4">
                            <dt class="text-ink-soft">{{ $label }}</dt>
                            <dd class="whitespace-pre-line break-words text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        @endforeach

        @isset($documents)
            @include('shared.attachments', $documents)
        @endisset
    </div>

    {{-- Kolom samping: label di atas nilai karena kolomnya sempit. --}}
    <div class="space-y-5">
        @foreach (collect($sections)->only($side) as $title => $rows)
            <x-card :title="$title">
                <dl class="space-y-3">
                    @foreach ($rows as $label => $value)
                        <div>
                            <dt class="text-caption text-ink-muted">{{ $label }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line break-words text-ink">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        @endforeach
    </div>
</div>

<p class="text-caption text-ink-muted">
    Terakhir diubah {{ Format::date($organization->updated_at) }}@if ($organization->updater) oleh {{ $organization->updater->name }}@endif.
</p>
