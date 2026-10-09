@props([
    'title',
    'lead' => null,
    // Opsional: sumber <picture> untuk latar foto + overlay (hanya Profil Ransiki); default forest polos.
    'image' => null,
])

{{-- Pita judul halaman publik berlatar forest. Slot "top" untuk remah/label, slot utama untuk isi tambahan. --}}
<section class="on-dark relative isolate overflow-hidden bg-forest text-paper">
    @if ($image)
        <x-public.picture :image="$image" alt="" eager width="1600" height="686" frame="absolute inset-0 -z-20 block size-full" class="size-full object-cover" />
        <div class="overlay-forest absolute inset-0 -z-10" aria-hidden="true"></div>
    @endif
    <div class="mx-auto max-w-public px-4 py-10 md:px-6 md:py-14">
        {{ $top ?? '' }}
        <h1 class="max-w-3xl text-display-sm font-semibold md:text-display">{{ $title }}</h1>
        @if ($lead)
            <p class="mt-3 max-w-[42rem] text-body text-forest-soft">{{ $lead }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
