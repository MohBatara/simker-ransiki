@props([
    // Khusus halaman form: di HP bar aksi menempel di atas navigasi bawah selama form digulir.
    'sticky' => false,
])

<div {{ $attributes->class([
    'flex flex-wrap items-center gap-2 md:justify-end max-md:*:flex-1',
    // --app-bottom dan --app-safe-bottom diatur layout app (berbeda saat mode focus).
    'max-md:sticky max-md:bottom-[var(--app-bottom,0px)] max-md:z-10 max-md:-mx-4 max-md:border-t max-md:border-line max-md:bg-paper max-md:px-4 max-md:pt-3 max-md:pb-[calc(0.75rem+var(--app-safe-bottom,0px))]' => $sticky,
]) }}>
    {{ $slot }}
</div>
