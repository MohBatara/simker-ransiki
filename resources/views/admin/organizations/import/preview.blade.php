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
    <form novalidate method="POST" action="{{ route('admin.organizations.import.run', $token) }}" class="space-y-6" x-data="{ ready: {{ $ready }}, picked: [] }">
        @csrf

        <p class="text-ui text-ink-soft">Data belum disimpan. Periksa setiap baris, centang baris peringatan yang tetap ingin diimpor, lalu tekan Impor.</p>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-admin.stat compact icon="check-circle" label="Siap" :value="$ready" tone="success" hint="akan diimpor" />
            <x-admin.stat compact icon="exclamation-triangle" label="Peringatan" :value="$counts[OrganizationImport::WARNING]" tone="warning" hint="dilewati kecuali dicentang" />
            <x-admin.stat compact icon="x-circle" label="Gagal" :value="$counts[OrganizationImport::FAILED]" tone="danger" hint="tidak diimpor" />
        </div>

        <x-admin.table>
            <x-slot:head>
                <x-admin.table.heading>Kelompok</x-admin.table.heading>
                <x-admin.table.heading>Baris</x-admin.table.heading>
                <x-admin.table.heading>Status</x-admin.table.heading>
                <x-admin.table.heading optional>Kampung</x-admin.table.heading>
                <x-admin.table.heading>Keterangan</x-admin.table.heading>
                <x-admin.table.heading>Tetap impor</x-admin.table.heading>
            </x-slot:head>

            @foreach ($rows as $row)
                <x-admin.table.row>
                    <x-admin.table.cell primary class="break-words">{{ $row['values']['name'] ?? '(nama kosong)' }}</x-admin.table.cell>
                    <x-admin.table.cell subtitle class="whitespace-nowrap">Baris {{ $row['line'] }}</x-admin.table.cell>
                    <x-admin.table.cell badge><x-admin.badge-status :tone="$statuses[$row['status']][1]" :label="$statuses[$row['status']][0]" /></x-admin.table.cell>
                    <x-admin.table.cell label="Kampung" optional>{{ $row['values']['village'] ?? '' }}</x-admin.table.cell>
                    <x-admin.table.cell label="Keterangan" class="max-md:items-start">
                        @if ($row['messages'])
                            <ul class="space-y-1 text-ink-soft max-md:text-right">
                                @foreach ($row['messages'] as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-ink-muted">Siap diimpor</span>
                        @endif
                    </x-admin.table.cell>
                    {{-- Di kartu HP baris ini hanya tampil untuk baris peringatan. --}}
                    <x-admin.table.cell label="Tetap impor" @class(['max-md:hidden' => $row['status'] !== OrganizationImport::WARNING])>
                        @if ($row['status'] === OrganizationImport::WARNING)
                            <x-checkbox name="include[]" :value="$row['line']" x-model.number="picked">
                                <span class="sr-only">Tetap impor baris {{ $row['line'] }}</span>
                            </x-checkbox>
                        @endif
                    </x-admin.table.cell>
                </x-admin.table.row>
            @endforeach
        </x-admin.table>

        <x-admin.action-bar>
            <x-button variant="secondary" :href="route('admin.organizations.import.create')">Unggah file lain</x-button>
            <x-button type="submit" processing="Mengimpor…" x-bind:disabled="ready + picked.length === 0">
                Impor <span x-text="ready + picked.length">{{ $ready }}</span> baris
            </x-button>
        </x-admin.action-bar>
    </form>
</x-layouts.app>
