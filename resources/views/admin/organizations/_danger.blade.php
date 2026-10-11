{{--
    Ubah status dan hapus kelompok (Admin). $part = 'menu' (item di menu "…" banner) atau 'modals' (konfirmasi).
    Kelompok yang punya laporan atau akun tidak bisa dihapus, hanya ditandai ditutup.
--}}
@use('App\Enums\OrganizationStatus')

@php
    $usage = collect([
        $organization->monthly_reports_count ? $organization->monthly_reports_count.' laporan' : null,
        $organization->users_count ? $organization->users_count.' akun' : null,
    ])->filter()->join(' dan ');
    $statusOptions = collect(OrganizationStatus::cases())
        ->reject(fn (OrganizationStatus $status) => $status === $organization->status)
        ->mapWithKeys(fn (OrganizationStatus $status) => [$status->value => $status->label()])
        ->all();
    $statusFailed = $errors->hasAny(['status', 'reason']);
@endphp

@if ($part === 'menu')
    @can('administer', $organization)
        <x-dropdown.item icon="arrow-path" x-on:click="$dispatch('open-modal', 'organization-status')">Ubah status</x-dropdown.item>
    @endcan
    @can('delete', $organization)
        @if (! $usage)
            <x-dropdown.item icon="trash" class="text-fix!" x-on:click="$dispatch('open-modal', 'delete-organization')">Hapus kelompok</x-dropdown.item>
        @endif
    @endcan
@else
    @can('administer', $organization)
        <x-modal name="organization-status" title="Ubah status kelompok" :show="$statusFailed">
            <form method="POST" action="{{ route('admin.organizations.status', $organization) }}" id="organization-status-form" class="space-y-4"
                  x-data="{ status: @js(old('status', array_key_first($statusOptions))) }">
                @csrf
                @method('PATCH')
                <p>Status saat ini: <strong class="text-ink">{{ $organization->status->label() }}</strong>.</p>
                @if ($usage)
                    <p>Kelompok ini punya {{ $usage }}, jadi tidak bisa dihapus. Bila sudah berhenti, tandai ditutup.</p>
                @endif
                <x-field label="Status baru" for="status">
                    <x-select name="status" :options="$statusOptions" :selected="old('status', array_key_first($statusOptions))" x-model="status" required />
                </x-field>
                @foreach ($statusOptions as $value => $label)
                    <p x-show="status === @js($value)" @if ($value !== old('status', array_key_first($statusOptions))) x-cloak @endif class="text-caption text-ink-soft">
                        {{ OrganizationStatus::from($value)->description() }}
                    </p>
                @endforeach
                <x-field label="Alasan" for="reason" hint="Contoh: pengurus menyatakan kelompok berhenti beroperasi sejak Agustus.">
                    <x-textarea name="reason" rows="3" required minlength="5" maxlength="500" />
                </x-field>
            </form>
            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'organization-status')">Batal</x-button>
                <x-button type="submit" form="organization-status-form">Ubah status</x-button>
            </x-slot:footer>
        </x-modal>
    @endcan
    @can('delete', $organization)
        @if (! $usage)
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
    @endcan
@endif
