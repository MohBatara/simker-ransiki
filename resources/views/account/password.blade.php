@php
    $user = auth()->user();
    $rows = [
        'Nama' => $user->name,
        'Username' => $user->username,
        'Peran' => $user->role->label(),
        'OPD' => $user->opd?->name,
        'Kelompok' => $user->organization?->name,
        'Email' => $user->email ?? \App\Support\Format::orEmpty(null),
        'Telepon' => \App\Support\Format::phone($user->phone) ?? \App\Support\Format::orEmpty(null),
    ];
@endphp

<x-layouts.app title="Akun saya" :description="$user->name.' · '.$user->role->label()">
    <div class="space-y-6">
        <x-admin.form-section title="Data akun" description="Nama, username, dan peran diatur oleh Admin distrik. Hubungi Admin bila ada yang perlu diubah.">
            <dl class="divide-y divide-line rounded-tile border border-line">
                @foreach (array_filter($rows, fn ($value) => $value !== null) as $label => $value)
                    <div class="grid gap-1 px-4 py-3 sm:grid-cols-[minmax(0,10rem)_1fr] sm:gap-4">
                        <dt class="text-ink-soft">{{ $label }}</dt>
                        <dd class="min-w-0 break-words text-ink">{{ \App\Support\Format::orEmpty($value) }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-admin.form-section>

        @include('account._password-form', ['page' => true])

        <x-admin.form-section title="Keluar" description="Keluar dari akun ini di perangkat yang sedang dipakai.">
            <form novalidate method="POST" action="{{ route('logout') }}" data-logout>
                @csrf
                <x-button variant="secondary" type="submit" icon="arrow-right-start-on-rectangle" processing="Keluar…">Keluar</x-button>
            </form>
        </x-admin.form-section>
    </div>
</x-layouts.app>
