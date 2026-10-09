{{--
    Di HP (< 768px) setiap baris tampil sebagai kartu; label kolom diambil dari atribut "label" pada x-table.cell.
    Kolom "optional" disembunyikan di tablet agar hanya kolom penting yang tampil.
--}}
<div {{ $attributes->class('md:overflow-x-auto md:rounded-card md:border md:border-line md:bg-paper') }}>
    <table class="w-full text-ui max-md:block">
        @isset($head)
            <thead class="max-md:hidden">
                <tr class="border-b border-line">{{ $head }}</tr>
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
