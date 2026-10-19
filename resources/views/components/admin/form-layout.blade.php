@props([
    // Form panjang (lebih dari 3 section): daftar section menempel di kolom kiri (desktop), bisa diklik untuk melompat,
    // dengan penanda section aktif saat menggulir (Alpine "formNav"). Di HP daftar disembunyikan.
    'nav' => false,
])

@if ($nav)
    <div x-data="formNav" data-nav {{ $attributes->class('group/form lg:grid lg:grid-cols-[280px_minmax(0,1fr)] lg:items-start lg:gap-8') }}>
        <nav aria-label="Bagian form" class="hidden lg:sticky lg:top-6 lg:block">
            <p class="mb-2 text-micro font-semibold tracking-wide text-ink-3 uppercase">Bagian form</p>
            <ol class="space-y-0.5 border-l border-line">
                <template x-for="section in sections" :key="section.id">
                    <li>
                        <a :href="'#' + section.id" x-on:click="go($event, section.id)"
                           :aria-current="active === section.id ? 'true' : null"
                           :class="active === section.id ? 'border-brand font-semibold text-brand' : 'border-transparent text-ink-2 hover:text-ink'"
                           class="-ml-px flex items-center gap-2 border-l-2 py-1.5 pl-3 text-caption transition-colors">
                            <span x-text="section.title"></span>
                            <span x-show="section.error" class="size-2 shrink-0 rounded-full bg-fix" aria-hidden="true"></span>
                            <span x-show="section.error" class="sr-only">(ada isian yang perlu diperbaiki)</span>
                        </a>
                    </li>
                </template>
            </ol>
        </nav>
        <div class="min-w-0 space-y-6">{{ $slot }}</div>
    </div>
@else
    <div {{ $attributes->class('space-y-6') }}>{{ $slot }}</div>
@endif
