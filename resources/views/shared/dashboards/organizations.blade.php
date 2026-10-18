{{--
    Kartu "Kelompok" dasbor Admin, tab Ringkasan: total, bar status bersegmen (Aktif brand, Tidak aktif sementara sand,
    Ditutup clay) dengan legenda yang bisa diklik, lalu daftar ringkas jenis kelembagaan (5 teratas, sisanya
    dibuka di tempat). Bidang usaha ada di tab Ekonomi dan filter daftar Kelompok. Variabel: $organizations (DistrictStats::organizations()).
--}}
@use('App\Support\Format')
@use('App\Enums\OrganizationStatus')

@php
    $total = $organizations['total'];
    $segments = collect([
        [OrganizationStatus::Active, 'Aktif', 'bg-brand'],
        [OrganizationStatus::Inactive, 'Tidak aktif', 'bg-sand'],
        [OrganizationStatus::Closed, 'Ditutup', 'bg-clay'],
    ])->map(fn ($segment) => [
        'label' => $segment[1],
        'full' => $segment[0]->label(),
        'color' => $segment[2],
        'value' => $organizations['statuses'][$segment[0]->value] ?? 0,
        'href' => route('admin.organizations.index', ['status' => $segment[0]->value]),
    ]);
    $lists = [
        ['title' => 'Jenis kelembagaan', 'items' => $organizations['types'], 'filter' => 'type', 'note' => null],
    ];
@endphp

<x-admin.card title="Kelompok" description="Semua kelompok terdaftar di Distrik Ransiki." stretch :class="$class ?? null">
    <x-slot:actions>
        <x-link :href="route('admin.organizations.index')" class="gap-1 text-ui">
            Lihat semua <x-heroicon-o-arrow-right class="size-4" aria-hidden="true" />
        </x-link>
    </x-slot:actions>

    <div class="space-y-6">
        <div class="space-y-3">
            <p class="flex flex-wrap items-baseline gap-x-2">
                <span class="text-[2.25rem] leading-[2.5rem] font-semibold tabular-nums text-ink">{{ Format::number($total) }}</span>
                <span class="text-ui text-ink-soft">kelompok terdaftar</span>
            </p>

            {{-- Bar status bersegmen 8px; tiap segmen menaut ke daftar Kelompok terfilter. --}}
            @if ($total > 0)
                <div class="flex h-2 overflow-hidden rounded-full bg-subtle">
                    @foreach ($segments->where('value', '>', 0) as $segment)
                        <a href="{{ $segment['href'] }}" class="{{ $segment['color'] }} h-full transition-opacity hover:opacity-80 focus-visible:outline-offset-1" style="width: {{ $segment['value'] / $total * 100 }}%" title="{{ $segment['full'] }}: {{ Format::number($segment['value']) }}" aria-label="{{ $segment['full'] }}: {{ Format::number($segment['value']) }} kelompok"></a>
                    @endforeach
                </div>
            @endif
            <ul class="flex flex-wrap items-center gap-x-4 gap-y-1 text-caption" aria-label="Status kelompok">
                @foreach ($segments as $segment)
                    <li>
                        <a href="{{ $segment['href'] }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-control text-ink-soft hover:text-ink hover:underline md:min-h-0" title="{{ $segment['full'] }}">
                            <span class="{{ $segment['color'] }} size-2 shrink-0 rounded-full" aria-hidden="true"></span>
                            {{ $segment['label'] }} <span class="font-semibold tabular-nums text-ink">{{ Format::number($segment['value']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        @foreach ($lists as $list)
            @php
                $items = collect($list['items'])->values();
                $max = max(1, (int) $items->max('value'));
            @endphp
            <section x-data="{ all: false }" class="border-t border-line pt-5">
                <h3 class="text-ui font-semibold text-ink">{{ $list['title'] }}</h3>
                @if ($list['note'])
                    <p class="text-caption text-ink-muted">{{ $list['note'] }}</p>
                @endif
                @if ($items->isEmpty())
                    <p class="mt-2 text-ui text-ink-soft">Belum ada data.</p>
                @else
                    <ul class="mt-2">
                        @foreach ($items as $item)
                            <li @if ($loop->index >= 5) x-cloak x-show="all" @endif>
                                <a href="{{ route('admin.organizations.index', [$list['filter'] => $item['id']]) }}" class="group flex min-h-11 flex-col justify-center gap-1 rounded-control py-1 md:min-h-9">
                                    <span class="flex items-baseline justify-between gap-3 text-ui">
                                        <span class="min-w-0 truncate text-ink group-hover:underline">{{ $item['label'] }}</span>
                                        <span class="shrink-0 font-semibold tabular-nums text-ink">{{ Format::number($item['value']) }}</span>
                                    </span>
                                    <span class="block h-1 overflow-hidden rounded-full bg-subtle" aria-hidden="true">
                                        <span class="block h-full rounded-full bg-brand" style="width: {{ $item['value'] / $max * 100 }}%"></span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if ($items->count() > 5)
                        <button type="button" x-on:click="all = ! all" x-bind:aria-expanded="all" class="mt-1 inline-flex min-h-11 items-center rounded-control text-ui font-semibold text-brand hover:text-brand-hover md:min-h-9">
                            <span x-text="all ? 'Tampilkan lebih sedikit' : 'Tampilkan semua ({{ $items->count() }})'">Tampilkan semua ({{ $items->count() }})</span>
                        </button>
                    @endif
                @endif
            </section>
        @endforeach
    </div>
</x-admin.card>
