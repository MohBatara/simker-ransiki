{{-- Tab Riwayat (Admin dan OPD pembina): perubahan profil, produk, dan izin. Variabel: $logs (paginator ChangeLog). --}}
@use('App\Support\Format')
@use('App\Support\ChangeLogger')

@if ($logs->isEmpty())
    <x-empty-state title="Belum ada riwayat perubahan" description="Setiap perubahan profil, produk, dan izin kelompok ini akan tercatat di sini." />
@else
    <x-table label="Riwayat perubahan">
        <x-slot:head>
            <x-table.heading>Perubahan</x-table.heading>
            <x-table.heading>Data</x-table.heading>
            <x-table.heading wrap>Sebelum</x-table.heading>
            <x-table.heading wrap>Sesudah</x-table.heading>
            <x-table.heading>Oleh</x-table.heading>
            <x-table.heading>Waktu</x-table.heading>
        </x-slot:head>

        @foreach ($logs as $log)
            <x-table.row>
                <x-table.cell primary>
                    {{ $log->field ? ChangeLogger::label($log->loggable_type, $log->field) : $log->action->label() }}
                </x-table.cell>
                <x-table.cell subtitle>{{ $log->subject }}</x-table.cell>
                <x-table.cell label="Sebelum" class="max-w-xs break-words whitespace-pre-line text-ink-soft">{{ $log->field ? ($log->old_value ?? Format::EMPTY) : '' }}</x-table.cell>
                <x-table.cell label="Sesudah" class="max-w-xs break-words whitespace-pre-line">{{ $log->field ? ($log->new_value ?? Format::EMPTY) : '' }}</x-table.cell>
                <x-table.cell label="Oleh">{{ $log->user?->nameWithAgency() ?? 'Sistem' }}</x-table.cell>
                <x-table.cell label="Waktu" class="whitespace-nowrap">{{ Format::relativeDateTime($log->created_at) }}</x-table.cell>
            </x-table.row>
        @endforeach

        @if ($logs->hasPages())
            <x-slot:footer>{{ $logs->links() }}</x-slot:footer>
        @endif
    </x-table>
@endif
