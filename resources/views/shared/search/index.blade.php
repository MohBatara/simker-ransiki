{{-- Hasil cari global (Admin, OPD). Variabel: $area, $query, $searching, $organizations, $products. --}}
@use('App\Support\Format')

<x-layouts.app title="Cari" :description="$searching ? 'Hasil untuk “'.$query.'”' : 'Cari kelompok, ketua, kampung, atau produk.'">
    <div class="space-y-5">
        <form method="GET" action="{{ route($area.'.search') }}" role="search" class="flex flex-wrap items-center gap-2">
            <x-search-input :value="$query" label="Cari kelompok, ketua, kampung, atau produk" class="flex-1 md:max-w-lg" />
            <x-button variant="ghost" type="submit">Cari</x-button>
        </form>

        @if (! $searching)
            <p class="text-ink-soft">Ketik paling sedikit 2 huruf.</p>
        @elseif ($organizations->isEmpty() && $products->isEmpty())
            <x-empty-state title="Tidak ada hasil untuk “{{ $query }}”" description="Coba kata lain, misalnya nama ketua, nama kampung, atau nama produk." />
        @else
            @if ($organizations->isNotEmpty())
                <x-card :title="'Kelompok ('.$organizations->count().($organizations->count() >= App\Http\Controllers\Shared\SearchController::LIMIT ? '+' : '').')'" flush>
                    <x-list flush>
                        @foreach ($organizations as $organization)
                            <x-list.item :href="route($area.'.organizations.show', $organization)">
                                <x-avatar :name="$organization->name" />
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-ink">{{ $organization->name }}</span>
                                    <span class="block text-caption text-ink-muted">
                                        {{ $organization->institutionType->name }} · {{ $organization->village->name }}@if ($organization->leader_name) · Ketua: {{ $organization->leader_name }}@endif
                                    </span>
                                </span>
                            </x-list.item>
                        @endforeach
                    </x-list>
                </x-card>
            @endif

            @if ($products->isNotEmpty())
                <x-card :title="'Produk ('.$products->count().($products->count() >= App\Http\Controllers\Shared\SearchController::LIMIT ? '+' : '').')'" flush>
                    <x-list flush>
                        @foreach ($products as $product)
                            <x-list.item :href="route($area.'.organizations.products', $product->organization_id)">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-ink">{{ $product->name }}</span>
                                    <span class="block text-caption text-ink-muted">{{ $product->organization->name }}@if ($product->unit) · {{ $product->unit }}@endif</span>
                                </span>
                            </x-list.item>
                        @endforeach
                    </x-list>
                </x-card>
            @endif
        @endif
    </div>
</x-layouts.app>
