{{--
    Kemungkinan data ganda: pasangan kelompok bernama mirip (App\Support\DuplicateCandidates::pairs).
    Variabel: $pairs (paginator), $strongCount.
--}}
@php
    $detail = fn (array $organization) => collect([$organization['type'], $organization['village'], $organization['status']])->filter()->join(' · ');
@endphp

<x-layouts.app
    title="Kemungkinan data ganda"
    description="Pasangan kelompok dengan nama mirip. Buka kedua kelompok lalu putuskan sendiri, misalnya tutup salah satu dengan alasan."
    section="kelompok"
    tab="ganda"
>
    <div class="space-y-4">
        @if ($pairs->total() > 0)
            <p class="text-ui text-ink-soft">
                {{ $pairs->total() }} pasangan · {{ $strongCount }} di kampung yang sama.
                Pasangan yang salah satunya sudah ditutup tidak ditampilkan.
            </p>
        @endif

        @if ($pairs->isEmpty())
            <x-empty-state
                title="Tidak ada kemungkinan data ganda"
                description="Pasangan kelompok dengan nama mirip akan muncul di sini. Pasangan yang ditandai bukan data ganda tidak muncul lagi."
            />
        @else
            <x-table>
                <x-slot:head>
                    <x-table.heading>Kelompok</x-table.heading>
                    <x-table.heading>Peringatan</x-table.heading>
                    <x-table.heading>Mirip dengan</x-table.heading>
                    <x-table.heading><span class="sr-only">Aksi</span></x-table.heading>
                </x-slot:head>

                @foreach ($pairs as $pair)
                    <x-table.row>
                        <x-table.cell primary>
                            <x-link :href="route('admin.organizations.show', $pair['first']['id'])" inline>{{ $pair['first']['name'] }}</x-link>
                            <span class="block text-caption font-normal text-ink-soft">{{ $detail($pair['first']) }}</span>
                        </x-table.cell>
                        <x-table.cell badge>
                            <x-badge :tone="$pair['strong'] ? 'wait' : 'idle'">{{ $pair['strong'] ? 'Kampung sama' : 'Kampung lain' }}</x-badge>
                        </x-table.cell>
                        <x-table.cell label="Mirip dengan" class="max-md:items-start!">
                            <span class="max-md:text-right">
                                <x-link :href="route('admin.organizations.show', $pair['second']['id'])" inline>{{ $pair['second']['name'] }}</x-link>
                                <span class="block text-caption text-ink-soft">{{ $detail($pair['second']) }}</span>
                            </span>
                        </x-table.cell>
                        <x-table.cell actions>
                            <form method="POST" action="{{ route('admin.organizations.duplicates.dismiss') }}" class="max-md:w-full">
                                @csrf
                                <input type="hidden" name="first" value="{{ $pair['first']['id'] }}">
                                <input type="hidden" name="second" value="{{ $pair['second']['id'] }}">
                                <x-button type="submit" variant="secondary" processing="Menyimpan…" class="max-md:w-full">Bukan data ganda</x-button>
                            </form>
                        </x-table.cell>
                    </x-table.row>
                @endforeach

                @if ($pairs->hasPages())
                    <x-slot:footer>{{ $pairs->links() }}</x-slot:footer>
                @endif
            </x-table>
        @endif
    </div>
</x-layouts.app>
