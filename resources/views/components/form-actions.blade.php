@props([
    // Khusus halaman form: di HP bar aksi menempel di atas navigasi bawah selama form digulir.
    'sticky' => false,
])

<div {{ $attributes->class([
    'flex flex-wrap items-center gap-2 md:justify-end max-md:*:flex-1',
    'max-md:sticky max-md:bottom-[calc(4rem+env(safe-area-inset-bottom))] max-md:z-10 max-md:-mx-4 max-md:border-t max-md:border-line max-md:bg-paper max-md:px-4 max-md:py-3' => $sticky,
]) }}>
    {{ $slot }}
</div>
