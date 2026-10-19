@props([
    // true bila baris memakai href (kolom titik tiga "Lihat" di kanan).
    'clickable' => false,
    // Di dalam x-admin.card: tanpa bingkai sendiri.
    'bare' => false,
    // Nama tabel untuk tabel yang bisa lebih lebar dari kartunya: wadah gulir menjadi region yang bisa difokuskan.
    // Tanpa label, kepala tabel menempel di atas layar saat digulir (sticky butuh wadah tanpa overflow).
    'label' => null,
])

{{--
    Tabel area aplikasi: bingkai line sudut 10px, kepala 40px berlatar head (11px kapital ink-3), baris 52px teks 13px
    dengan pemisah line-soft dan hover tipis, angka rata kanan tabular, kolom terakhir tombol titik tiga (menu aksi).
    HP (< 768px): setiap baris menjadi kartu berlabel. Slot "footer" untuk paginasi (x-admin.pagination).
--}}
<div
    @if ($label) role="region" aria-label="{{ $label }}" tabindex="0" @endif
    {{ $attributes->class([
        'md:overflow-x-auto focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand' => $label,
        'md:rounded-table md:border md:border-line md:bg-panel' => ! $bare,
        'md:border-t md:border-line max-md:px-4 max-md:pb-4' => $bare,
    ]) }}
>
    <table class="w-full text-caption max-md:block">
        @isset($head)
            <thead class="max-md:hidden">
                <tr>
                    {{ $head }}
                    @if ($clickable)
                        <th scope="col" @class(['w-12 border-b border-line bg-head', 'md:sticky md:top-0 md:z-10' => ! $label, 'rounded-tr-[9px]' => ! $bare])><span class="sr-only">Tindakan</span></th>
                    @endif
                </tr>
            </thead>
        @endisset
        <tbody class="max-md:block max-md:space-y-3 md:divide-y md:divide-line-soft">
            {{ $slot }}
        </tbody>
    </table>

    @isset($footer)
        <div class="border-line-soft max-md:mt-4 md:border-t md:px-4 md:py-3">{{ $footer }}</div>
    @endisset
</div>
