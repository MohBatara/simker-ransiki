{{--
    Tutup/hapus kelompok (Admin). $part = 'menu' (item di menu "…" banner) atau 'modals' (konfirmasi).
    Kelompok yang punya laporan atau akun tidak bisa dihapus, hanya ditandai ditutup.
--}}
@php
    $usage = collect([
        $organization->monthly_reports_count ? $organization->monthly_reports_count.' laporan' : null,
        $organization->users_count ? $organization->users_count.' akun' : null,
    ])->filter()->join(' dan ');
    $closed = $organization->status === App\Enums\OrganizationStatus::Closed;
@endphp

@can('delete', $organization)
    @if ($part === 'menu')
        @if ($usage && ! $closed)
            <x-dropdown.item icon="archive-box" x-on:click="$dispatch('open-modal', 'close-organization')">Tandai ditutup</x-dropdown.item>
        @elseif (! $usage)
            <x-dropdown.item icon="trash" class="text-fix!" x-on:click="$dispatch('open-modal', 'delete-organization')">Hapus kelompok</x-dropdown.item>
        @endif
    @else
        @if ($usage && ! $closed)
            <x-modal name="close-organization" title="Tandai ditutup">
                <p>Kelompok ini punya {{ $usage }}, jadi tidak bisa dihapus. Kelompok yang ditutup tidak dihitung wajib lapor dan tidak tampil di direktori publik. Data dan laporan lamanya tetap tersimpan.</p>
                <x-slot:footer>
                    <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'close-organization')">Batal</x-button>
                    <form method="POST" action="{{ route('admin.organizations.status', $organization) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="closed">
                        <x-button type="submit">Tandai ditutup</x-button>
                    </form>
                </x-slot:footer>
            </x-modal>
        @elseif (! $usage)
            <x-modal name="delete-organization" title="Hapus kelompok">
                <p>Hapus <strong class="text-ink">{{ $organization->name }}</strong> beserta produk, izin, dan lampirannya? Data yang dihapus tidak bisa dikembalikan.</p>
                <x-slot:footer>
                    <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'delete-organization')">Batal</x-button>
                    <form method="POST" action="{{ route('admin.organizations.destroy', $organization) }}">
                        @csrf
                        @method('DELETE')
                        <x-button variant="danger" type="submit" processing="Menghapus…">Hapus</x-button>
                    </form>
                </x-slot:footer>
            </x-modal>
        @endif
    @endif
@endcan
