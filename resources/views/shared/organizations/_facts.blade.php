{{--
    Baris fakta kunci di header detail kelompok. Variabel: $organization (opds, *_count), $latestReport, $area.
--}}
@use('App\Support\Format')

<dl class="flex flex-wrap gap-x-6 gap-y-3 text-ui">
    <div>
        <dt class="text-caption text-ink-muted">OPD pembina</dt>
        <dd class="mt-0.5">
            @if ($organization->opds->isEmpty())
                <x-badge tone="wait">Belum ada OPD pembina</x-badge>
            @else
                <span class="text-ink">{{ $organization->opds->map(fn ($opd) => $opd->short_name ?: $opd->name)->join(', ') }}</span>
            @endif
        </dd>
    </div>
    <div>
        <dt class="text-caption text-ink-muted">Laporan terakhir</dt>
        <dd class="mt-0.5 flex flex-wrap items-center gap-2">
            @if ($latestReport)
                @if ($latestReport->submitted_at)
                    <x-link href="{{ route($area.'.reports.show', [$latestReport, 'dari' => 'kelompok']) }}" class="font-normal">{{ Format::month($latestReport->period) }}</x-link>
                @else
                    <span class="text-ink">{{ Format::month($latestReport->period) }}</span>
                @endif
                <x-badge :tone="$latestReport->status->badgeColor()">{{ $latestReport->status->reviewerLabel() }}</x-badge>
            @else
                <span class="text-ink-soft">Belum ada laporan</span>
            @endif
        </dd>
    </div>
    <div>
        <dt class="text-caption text-ink-muted">Produk</dt>
        <dd class="mt-0.5"><x-link href="{{ route($area.'.organizations.products', $organization) }}" class="font-normal tabular-nums">{{ $organization->products_count }} produk</x-link></dd>
    </div>
    @if ($area === 'admin')
        <div>
            <dt class="text-caption text-ink-muted">Akun</dt>
            <dd class="mt-0.5 flex flex-wrap items-center gap-2">
                @if ($organization->users_count === 0)
                    <x-badge tone="wait">Belum ada akun</x-badge>
                    <x-link href="{{ route('admin.users.create', ['kelompok' => $organization->id]) }}">Buat akun</x-link>
                @else
                    <x-link href="{{ route('admin.organizations.users', $organization) }}" class="font-normal tabular-nums">{{ $organization->users_count }} akun</x-link>
                @endif
            </dd>
        </div>
    @endif
</dl>
