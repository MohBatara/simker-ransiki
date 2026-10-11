@props([
    // Isi true bila baris memakai href, agar kepala tabel punya kolom untuk chevron.
    'clickable' => false,
    // Di dalam kartu: tanpa bingkai sendiri agar tidak berbingkai ganda.
    'bare' => false,
    // Nama tabel untuk pembaca layar. Diisi pada tabel yang bisa lebih lebar dari kartunya: wadah gulir
    // menjadi region yang bisa difokuskan dan digeser dengan keyboard.
    'label' => null,
])

{{--
    Di HP (< 768px) setiap baris tampil sebagai kartu: sel "primary" dan "badge" di baris atas,
    sel "subtitle" di bawahnya, sel lain menjadi baris label-nilai (label dari atribut "label").
    Kolom "optional" disembunyikan di tablet agar hanya kolom penting yang tampil.
--}}
<div
    @if ($label) role="region" aria-label="{{ $label }}" tabindex="0" @endif
    {{ $attributes->class(['md:overflow-x-auto', 'md:rounded-card md:border md:border-line md:bg-paper' => ! $bare, 'focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand' => $label]) }}
>
    <table class="w-full text-ui max-md:block">
        @isset($head)
            <thead class="max-md:hidden">
                <tr class="border-b border-line">
                    {{ $head }}
                    @if ($clickable)
                        <th scope="col" class="w-10"><span class="sr-only">Detail</span></th>
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
