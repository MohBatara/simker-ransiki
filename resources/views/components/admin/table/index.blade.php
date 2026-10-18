@props([
    // true bila baris memakai href (kolom chevron di kanan).
    'clickable' => false,
    // Di dalam x-admin.card: tanpa bingkai sendiri.
    'bare' => false,
    // Nama tabel untuk tabel yang bisa lebih lebar dari kartunya: wadah gulir menjadi region yang bisa difokuskan.
    // Tanpa label, kepala tabel menempel di bawah bilah atas saat digulir (sticky butuh wadah tanpa overflow).
    'label' => null,
])

{{--
    Tabel Admin/OPD: kepala kecil kapital muted yang menempel, baris 56px dengan hover tint, angka rata kanan tabular,
    kolom aksi "…" (x-admin.table.actions). HP (< 768px): setiap baris menjadi kartu berlabel.
    Slot "footer" untuk paginasi (x-admin.pagination).
--}}
<div
    @if ($label) role="region" aria-label="{{ $label }}" tabindex="0" @endif
    {{ $attributes->class([
        'md:overflow-x-auto focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand' => $label,
        'md:rounded-tile md:border md:border-line md:bg-paper' => ! $bare,
    ]) }}
>
    <table class="w-full text-ui max-md:block">
        @isset($head)
            <thead class="max-md:hidden">
                <tr>
                    {{ $head }}
                    @if ($clickable)
                        <th scope="col" @class(['w-10 border-b border-line bg-paper', 'sticky top-16 z-10' => ! $label, 'rounded-tr-tile' => ! $bare])><span class="sr-only">Detail</span></th>
                    @endif
                </tr>
            </thead>
        @endisset
        <tbody class="max-md:block max-md:space-y-3 md:divide-y md:divide-line">
            {{ $slot }}
        </tbody>
    </table>

    @isset($footer)
        <div class="border-line max-md:mt-4 md:border-t md:px-4 md:py-3">{{ $footer }}</div>
    @endisset
</div>
