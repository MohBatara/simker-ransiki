@use('App\Support\Format')

<x-layouts.public title="Profil Distrik Ransiki" :description="Format::excerpt($settings['profile_body'], 160) ?? 'Profil dan potensi ekonomi Distrik Ransiki.'">
    <x-public.band title="Profil Distrik Ransiki" :image="$cover" :crumbs="['Profil Ransiki' => null]">
        @if ($updatedAt)
            <p class="mt-3 text-caption text-paper">Terakhir diperbarui {{ Format::date($updatedAt) }}</p>
        @endif
    </x-public.band>

    <div class="mx-auto grid max-w-public gap-10 px-4 py-10 md:px-6 lg:grid-cols-[minmax(0,1fr)_17rem] lg:gap-14">
        <article>
            @if ($settings['profile_body'])
                <div class="prose-public">{!! Format::markdown($settings['profile_body']) !!}</div>
            @else
                <p class="text-body text-ink-soft">Profil distrik sedang disiapkan.</p>
            @endif

            @if ($settings['economic_potential'])
                <h2 class="mt-12 font-serif text-display-sm font-semibold text-ink">Potensi ekonomi</h2>
                <div class="prose-public mt-4">{!! Format::markdown($settings['economic_potential']) !!}</div>
            @endif
        </article>

        <aside class="on-dark h-fit rounded-public bg-forest p-4 text-paper shadow-card md:p-5" aria-label="Sekilas">
            <h2 class="font-serif text-card-title font-semibold">Sekilas</h2>
            @include('publik._stats', ['tone' => 'dark', 'class' => 'mt-3 grid-cols-3! lg:grid-cols-1! gap-3'])
            @if ($sectors->isNotEmpty())
                <ul class="mt-5 space-y-1.5 border-t border-forest-line pt-4 text-ui">
                    @foreach ($sectors as $sector)
                        <li class="flex justify-between gap-3">
                            <a href="{{ route('public.directory', ['bidang' => $sector->id]) }}" class="rounded-control text-paper hover:text-sun">{{ $sector->name }}</a>
                            <span class="text-sun tabular-nums">{{ Format::number($sector->organizations_count) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('public.directory') }}" class="mt-5 inline-flex items-center gap-2 rounded-control text-ui font-semibold text-sun hover:underline">Buka direktori kelompok <x-heroicon-o-arrow-right class="size-4" /></a>
        </aside>
    </div>
</x-layouts.public>
