@props([
    'title',
    'description' => null,
    // Label kecil di atas judul (mis. "Dasbor").
    'eyebrow' => null,
    // Remah: list of ['label' => ..., 'href' => ?string]; item terakhir = halaman ini.
    'breadcrumbs' => null,
    // Layout app: di HP judul sudah tampil di header atas, jadi h1 hanya untuk pembaca layar di HP.
    'condensed' => false,
])

{{--
    Kepala halaman di dalam panel putih: remah kecil, judul 22px semibold + subjudul 13px ink-2 di kiri. Di kanan: slot
    "tools" (cari ringkas + lonceng, tablet/desktop), lalu aksi. Slot "actions" = aksi utama (selalu terlihat); slot
    "secondary" = tombol bergaris, sebaris di tablet/desktop dan masuk menu "…" di HP. Slot "badge" di samping judul,
    "meta" di bawah keterangan.
--}}
<header {{ $attributes->class('flex flex-col gap-4 md:flex-row md:items-start md:justify-between') }}>
    <div class="min-w-0 flex-1">
        @if ($breadcrumbs)
            <x-breadcrumbs :items="$breadcrumbs" class="mb-2" />
        @endif
        @if ($eyebrow)
            <p @class(['text-caption font-semibold text-brand', 'max-md:hidden' => $condensed])>{{ $eyebrow }}</p>
        @endif
        <div @class(['flex flex-wrap items-center gap-x-3 gap-y-1', 'max-md:hidden' => $condensed && ! isset($badge)])>
            <h1 @class(['text-admin-title font-semibold text-ink', 'max-md:sr-only' => $condensed])>{{ $title }}</h1>
            @isset($badge)
                <div class="flex flex-wrap items-center gap-2">{{ $badge }}</div>
            @endisset
        </div>
        @if ($description)
            <p class="mt-0.5 max-w-3xl text-caption text-ink-2">{{ $description }}</p>
        @endif
        @isset($meta)
            <div class="mt-3">{{ $meta }}</div>
        @endisset
    </div>

    @if (isset($actions) || isset($secondary) || isset($tools))
        <div @class(['flex shrink-0 flex-wrap items-center gap-2 md:justify-end', 'max-md:hidden' => ! isset($actions) && ! isset($secondary)])>
            @isset($tools)
                <div class="hidden items-center gap-2 md:flex">{{ $tools }}</div>
            @endisset
            @isset($secondary)
                {{-- Tablet/desktop: tombol bergaris sebaris. HP: menu "…" berisi tombol yang sama sebagai daftar. --}}
                <div class="hidden flex-wrap items-center gap-2 md:flex">{{ $secondary }}</div>
                <div x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false" class="relative md:hidden">
                    <x-button variant="secondary" class="px-3" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="menu" aria-label="Menu tindakan">
                        <x-heroicon-o-ellipsis-vertical class="size-5" />
                    </x-button>
                    <div x-cloak x-show="open" x-on:click="open = false" class="absolute left-0 z-30 mt-1 grid w-60 rounded-card border border-line bg-paper py-1 shadow-float [&>*]:h-11 [&>*]:w-full [&>*]:justify-start [&>*]:rounded-none [&>*]:border-0 [&>*]:bg-transparent [&>*]:px-3 [&>*]:font-normal [&>*]:hover:bg-subtle">
                        {{ $secondary }}
                    </div>
                </div>
            @endisset
            {{ $actions ?? '' }}
        </div>
    @endif
</header>
