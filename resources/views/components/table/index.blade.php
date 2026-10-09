@props([
    // Isi true bila baris memakai href, agar kepala tabel punya kolom untuk chevron.
    'clickable' => false,
])

{{--
    Di HP (< 768px) setiap baris tampil sebagai kartu: sel "primary" dan "badge" di baris atas,
    sel "subtitle" di bawahnya, sel lain menjadi baris label-nilai (label dari atribut "label").
    Kolom "optional" disembunyikan di tablet agar hanya kolom penting yang tampil.
--}}
<div {{ $attributes->class('md:overflow-x-auto md:rounded-card md:border md:border-line md:bg-paper') }}>
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
