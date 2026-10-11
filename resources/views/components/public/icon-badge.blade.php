@props([
    // "lucide:nama" atau "heroicon:nama" (App\Support\PublicIcons).
    'icon',
    // 'edge' = lencana di tepi foto (bercincin putih), 'plain' = lingkaran brand-tint di kartu.
    'variant' => 'edge',
])

<span {{ $attributes->class([
    'flex shrink-0 items-center justify-center rounded-full',
    'size-12 border-4 border-paper bg-brand text-paper' => $variant === 'edge',
    'size-12 bg-brand-tint text-brand' => $variant === 'plain',
]) }} aria-hidden="true">
    <x-public.icon :name="$icon" class="size-5" />
</span>
