@php
    $usage = collect([
        $organization->monthly_reports_count ? $organization->monthly_reports_count.' laporan' : null,
        $organization->users_count ? $organization->users_count.' akun' : null,
    ])->filter()->join(' dan ');
    $closed = $organization->status === App\Enums\OrganizationStatus::Closed;
@endphp

@can('delete', $organization)
    <x-card title="Tutup atau hapus kelompok">
        @if ($usage)
            <p class="text-ink-soft">Kelompok ini punya {{ $usage }}, jadi tidak bisa dihapus.</p>
            @if ($closed)
                <p class="mt-1 text-ink-soft">Kelompok sudah ditutup. Untuk mengaktifkan kembali, ubah status di halaman ubah profil.</p>
            @else
                <p class="mt-1 text-ink-soft">Tandai sebagai ditutup bila kelompok sudah berhenti beroperasi.</p>
                <x-button variant="secondary" class="mt-4" x-data x-on:click="$dispatch('open-modal', 'close-organization')">Tandai ditutup</x-button>
            @endif
        @else
            <p class="text-ink-soft">Kelompok ini belum punya laporan atau akun, jadi masih bisa dihapus.</p>
            <x-button variant="danger" class="mt-4" x-data x-on:click="$dispatch('open-modal', 'delete-organization')">Hapus kelompok</x-button>
        @endif
    </x-card>

    @if ($usage && ! $closed)
    <x-modal name="close-organization" title="Tandai ditutup">
        <p>Kelompok yang ditutup tidak dihitung wajib lapor dan tidak tampil di direktori publik. Data dan laporan lamanya tetap tersimpan.</p>
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
    @endif

    @unless ($usage)
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
    @endunless
@endcan
