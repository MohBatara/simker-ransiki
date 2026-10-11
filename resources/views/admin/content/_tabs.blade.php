{{-- Tab Konten Website. Variabel: $active ('beranda' | 'halaman'). --}}
<x-tabs
    :tabs="['beranda' => 'Beranda', 'halaman' => 'Halaman & kontak']"
    :links="['beranda' => route('admin.content.edit'), 'halaman' => route('admin.content.pages')]"
    :active="$active"
/>
