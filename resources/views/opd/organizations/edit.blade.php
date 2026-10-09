<x-layouts.app
    title="Ubah profil kelompok"
    :description="$organization->name"
    :back="route('opd.organizations.show', $organization)"
    focus
>
    <x-empty-state
        title="Formulir profil belum tersedia"
        description="Formulir untuk melengkapi profil, produk, dan izin kelompok binaan sedang disiapkan."
    />
</x-layouts.app>
