{{--
    Impor kelompok, langkah 2: pratinjau. Belum ada yang disimpan. Baris "Siap" selalu diimpor, baris
    "Peringatan" hanya bila dicentang, baris "Gagal" tidak pernah.
    Variabel: $token, $fileName, $rows, $counts.
--}}
@use('App\Support\Imports\OrganizationImport')

@php
    $statuses = [
        OrganizationImport::READY => ['Siap', 'success'],
        OrganizationImport::WARNING => ['Peringatan', 'warning'],
        OrganizationImport::FAILED => ['Gagal', 'danger'],
    ];
    $ready = $counts[OrganizationImport::READY];
@endphp

<x-layouts.app
    title="Pratinjau impor"
    :description="$fileName"
    focus
    :back="route('admin.organizations.import.create')"
    :breadcrumbs="[
        ['label' => 'Kelompok', 'href' => route('admin.organizations.index')],
        ['label' => 'Impor', 'href' => route('admin.organizations.import.create')],
        ['label' => 'Pratinjau'],
    ]"
>
    <form method="POST" action="{{ route('admin.organizations.import.run', $token) }}" class="space-y-5" x-data="{ ready: {{ $ready }}, picked: [] }">
        @csrf

        <x-alert>Data belum disimpan. Periksa setiap baris, centang baris peringatan yang tetap ingin diimpor, lalu tekan Impor.</x-alert>

        <div class="grid grid-cols-3 gap-3 md:gap-4">
            <x-stat label="Siap" :value="$ready" tone="ok" hint="akan diimpor" />
            <x-stat label="Peringatan" :value="$counts[OrganizationImport::WARNING]" tone="wait" hint="dilewati kecuali dicentang" />
            <x-stat label="Gagal" :value="$counts[OrganizationImport::FAILED]" tone="fix" hint="tidak diimpor" />
        </div>

        <x-table>
            <x-slot:head>
                <x-table.heading>Kelompok</x-table.heading>
                <x-table.heading>Baris</x-table.heading>
                <x-table.heading>Status</x-table.heading>
                <x-table.heading optional>Kampung</x-table.heading>
                <x-table.heading>Keterangan</x-table.heading>
                <x-table.heading>Tetap impor</x-table.heading>
            </x-slot:head>

            @foreach ($rows as $row)
                <x-table.row>
                    <x-table.cell primary class="break-words">{{ $row['values']['name'] ?? '(nama kosong)' }}</x-table.cell>
                    <x-table.cell subtitle class="whitespace-nowrap">Baris {{ $row['line'] }}</x-table.cell>
                    <x-table.cell badge><x-admin.badge-status :tone="$statuses[$row['status']][1]" :label="$statuses[$row['status']][0]" /></x-table.cell>
                    <x-table.cell label="Kampung" optional>{{ $row['values']['village'] ?? '' }}</x-table.cell>
                    <x-table.cell label="Keterangan" class="max-md:items-start">
                        @if ($row['messages'])
                            <ul class="space-y-1 text-ink-soft max-md:text-right">
                                @foreach ($row['messages'] as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-ink-muted">Siap diimpor</span>
                        @endif
                    </x-table.cell>
                    {{-- Di kartu HP baris ini hanya tampil untuk baris peringatan. --}}
                    <x-table.cell label="Tetap impor" @class(['max-md:hidden' => $row['status'] !== OrganizationImport::WARNING])>
                        @if ($row['status'] === OrganizationImport::WARNING)
                            <x-checkbox name="include[]" :value="$row['line']" x-model.number="picked">
                                <span class="sr-only">Tetap impor baris {{ $row['line'] }}</span>
                            </x-checkbox>
                        @endif
                    </x-table.cell>
                </x-table.row>
            @endforeach
        </x-table>

        <x-form-actions sticky>
            <x-button variant="ghost" :href="route('admin.organizations.import.create')">Unggah file lain</x-button>
            <x-button type="submit" processing="Mengimpor…" x-bind:disabled="ready + picked.length === 0">
                Impor <span x-text="ready + picked.length">{{ $ready }}</span> baris
            </x-button>
        </x-form-actions>
    </form>
</x-layouts.app>
