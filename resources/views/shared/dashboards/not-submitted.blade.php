{{--
    Kelompok wajib lapor yang belum mengajukan laporan periode ini (setelah tenggat: "Terlambat melapor"). Maks 5 baris
    bertumpuk: nama (maks 2 baris) + satu pil keadaan (Draf, belum diajukan / Belum diisi; "Terlambat" sudah di judul),
    lalu "Ketua: … · 0812 0000 1005" (tel:), lalu tombol kecil "Salin pesan pengingat". "Lihat semua (N)" ke daftar Kelompok
    tersaring (memuat juga kelompok yang belum membuat laporan, yang tidak ada di daftar Laporan). Kartu tidak dipaksa
    setinggi kartu sebelahnya (lg:self-start). Variabel: $notSubmitted, $progress, $overdue, $period, $area, $class.
--}}
@use('App\Support\Format')
@use('App\Support\ReminderMessage')
@use('App\Support\Dashboards\ReportingPeriod')

@php
    $deadline = App\Models\MonthlyReport::deadlineFor($period);
    $total = $progress['draft'] + $progress['missing'];
    $late = $overdue['past_deadline'];
    $limit = 5;
@endphp

<x-admin.card
    :title="($late ? 'Terlambat melapor ' : 'Belum mengajukan ').Format::month($period)"
    :description="'Tenggat '.Format::date($deadline).' · '.ReportingPeriod::deadlineDistance($deadline)"
    id="belum-melapor"
    :class="trim(($class ?? '').' lg:self-start')"
>
    @if ($notSubmitted->isEmpty())
        <p class="flex items-center gap-2 text-ui text-ink-2" data-all-submitted>
            <x-heroicon-o-check-circle class="size-5 shrink-0 text-ok" aria-hidden="true" />
            {{ $late ? 'Semua kelompok sudah melapor tepat waktu.' : 'Semua kelompok wajib lapor sudah mengajukan laporan bulan ini.' }}
        </p>
    @else
        <ul class="-my-1 divide-y divide-line-soft" data-not-submitted>
            @foreach ($notSubmitted->take($limit) as $row)
                @php
                    $organization = $row['organization'];
                    $phone = Format::phone($organization->phone);
                @endphp
                <li class="space-y-1.5 py-3 first:pt-1 last:pb-1">
                    <div class="flex items-start justify-between gap-3">
                        <x-link :href="route($area.'.organizations.reports', $organization)" muted class="min-w-0 text-ink">
                            <span class="line-clamp-2 break-words" title="{{ $organization->name }}">{{ $organization->name }}</span>
                        </x-link>
                        <x-admin.badge-status tone="neutral" :label="$row['report'] ? 'Draf, belum diajukan' : 'Belum diisi'" class="mt-0.5 shrink-0" />
                    </div>
                    @if ($organization->leader_name || $phone)
                        <p class="text-label text-ink-2">
                            Ketua: {{ $organization->leader_name ?: 'belum diisi' }}
                            @if ($phone)
                                · <a href="{{ Format::telUrl($organization->phone) }}" class="whitespace-nowrap text-brand hover:underline">{{ $phone }}</a>
                            @endif
                        </p>
                    @endif
                    <div x-data="copyText({{ \Illuminate\Support\Js::from(ReminderMessage::for($organization, $period, $row['report'], auth()->user())) }})">
                        <x-button variant="secondary" icon="clipboard-document" x-on:click="copy()" class="md:h-8 md:px-2.5">
                            <span x-text="copied ? 'Pesan disalin' : 'Salin pesan pengingat'">Salin pesan pengingat</span>
                            <span class="sr-only"> untuk {{ $organization->name }}</span>
                        </x-button>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($total > $limit)
            <x-link :href="route($area.'.organizations.index', ['laporan' => $late ? 'terlambat' : 'belum-diajukan', 'periode' => $period->format('Y-m')])" class="mt-3">
                Lihat semua ({{ Format::number($total) }})
            </x-link>
        @endif
    @endif
</x-admin.card>
