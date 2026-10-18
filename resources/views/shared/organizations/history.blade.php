{{-- Tab Riwayat (Admin dan OPD pembina): perubahan profil, produk, dan izin. Variabel: $logs (paginator ChangeLog). --}}
@use('App\Support\Format')
@use('App\Support\ChangeLogger')

@if ($logs->isEmpty())
    <x-admin.empty title="Belum ada riwayat perubahan" description="Setiap perubahan profil, produk, dan izin kelompok ini akan tercatat di sini." />
@else
    <x-admin.table label="Riwayat perubahan">
        <x-slot:head>
            <x-admin.table.heading>Perubahan</x-admin.table.heading>
            <x-admin.table.heading>Data</x-admin.table.heading>
            <x-admin.table.heading wrap>Sebelum</x-admin.table.heading>
            <x-admin.table.heading wrap>Sesudah</x-admin.table.heading>
            <x-admin.table.heading>Oleh</x-admin.table.heading>
            <x-admin.table.heading>Waktu</x-admin.table.heading>
        </x-slot:head>

        @foreach ($logs as $log)
            <x-admin.table.row>
                <x-admin.table.cell primary>
                    {{ $log->field ? ChangeLogger::label($log->loggable_type, $log->field) : $log->action->label() }}
                </x-admin.table.cell>
                <x-admin.table.cell subtitle>{{ $log->subject }}</x-admin.table.cell>
                <x-admin.table.cell label="Sebelum" class="max-w-xs break-words whitespace-pre-line text-ink-soft">{{ $log->field ? ($log->old_value ?? Format::EMPTY) : '' }}</x-admin.table.cell>
                <x-admin.table.cell label="Sesudah" class="max-w-xs break-words whitespace-pre-line">{{ $log->field ? ($log->new_value ?? Format::EMPTY) : '' }}</x-admin.table.cell>
                <x-admin.table.cell label="Oleh">{{ $log->user?->nameWithAgency() ?? 'Sistem' }}</x-admin.table.cell>
                <x-admin.table.cell label="Waktu"><x-admin.date :value="$log->created_at" time /></x-admin.table.cell>
            </x-admin.table.row>
        @endforeach

        @if ($logs->hasPages())
            <x-slot:footer><x-admin.pagination :paginator="$logs" noun="perubahan" /></x-slot:footer>
        @endif
    </x-admin.table>
@endif
