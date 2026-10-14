{{-- Tab Konten Website. Variabel: $active ('beranda' | 'profil' | 'halaman'). --}}
<x-tabs
    :tabs="['beranda' => 'Beranda', 'profil' => 'Profil Ransiki', 'halaman' => 'Halaman & kontak']"
    :links="['beranda' => route('admin.content.edit'), 'profil' => route('admin.content.profile'), 'halaman' => route('admin.content.pages')]"
    :active="$active"
/>
