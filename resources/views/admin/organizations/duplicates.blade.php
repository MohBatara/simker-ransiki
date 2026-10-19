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
            <x-admin.empty
                icon="document-duplicate"
                title="Tidak ada kemungkinan data ganda"
                description="Pasangan kelompok dengan nama mirip akan muncul di sini. Pasangan yang ditandai bukan data ganda tidak muncul lagi."
            />
        @else
            <x-admin.table>
                <x-slot:head>
                    <x-admin.table.heading>Kelompok</x-admin.table.heading>
                    <x-admin.table.heading>Peringatan</x-admin.table.heading>
                    <x-admin.table.heading>Mirip dengan</x-admin.table.heading>
                    <x-admin.table.heading><span class="sr-only">Aksi</span></x-admin.table.heading>
                </x-slot:head>

                @foreach ($pairs as $pair)
                    <x-admin.table.row>
                        <x-admin.table.cell primary>
                            <x-link :href="route('admin.organizations.show', $pair['first']['id'])" inline>{{ $pair['first']['name'] }}</x-link>
                            <span class="block text-caption font-normal text-ink-soft">{{ $detail($pair['first']) }}</span>
                        </x-admin.table.cell>
                        <x-admin.table.cell badge>
                            <x-admin.badge-status :tone="$pair['strong'] ? 'warning' : 'neutral'" :label="$pair['strong'] ? 'Kampung sama' : 'Kampung lain'" />
                        </x-admin.table.cell>
                        <x-admin.table.cell label="Mirip dengan" class="max-md:items-start!">
                            <span class="max-md:text-right">
                                <x-link :href="route('admin.organizations.show', $pair['second']['id'])" inline>{{ $pair['second']['name'] }}</x-link>
                                <span class="block text-caption text-ink-soft">{{ $detail($pair['second']) }}</span>
                            </span>
                        </x-admin.table.cell>
                        <x-admin.table.cell actions>
                            <form novalidate method="POST" action="{{ route('admin.organizations.duplicates.dismiss') }}" class="max-md:w-full">
                                @csrf
                                <input type="hidden" name="first" value="{{ $pair['first']['id'] }}">
                                <input type="hidden" name="second" value="{{ $pair['second']['id'] }}">
                                <x-button type="submit" variant="secondary" processing="Menyimpan…" class="max-md:w-full">Bukan data ganda</x-button>
                            </form>
                        </x-admin.table.cell>
                    </x-admin.table.row>
                @endforeach

                <x-slot:footer>
                    <x-admin.pagination :paginator="$pairs" noun="pasangan" />
                </x-slot:footer>
            </x-admin.table>
        @endif
    </div>
</x-layouts.app>
