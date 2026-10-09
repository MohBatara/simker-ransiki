@use('App\Support\Format')

<x-layouts.public title="Profil Distrik Ransiki" :description="Format::excerpt($settings['profile_body'], 160) ?? 'Profil dan potensi ekonomi Distrik Ransiki.'">
    <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_17rem] lg:gap-14">
        <article>
            <h1 class="text-display-sm font-semibold text-ink md:text-display">Profil Distrik Ransiki</h1>
            @if ($updatedAt)
                <p class="mt-2 text-caption text-ink-muted">Terakhir diperbarui {{ Format::date($updatedAt) }}</p>
            @endif

            @if ($settings['profile_body'])
                <div class="prose-public mt-6">{!! Format::markdown($settings['profile_body']) !!}</div>
            @else
                <p class="mt-6 text-body text-ink-soft">Profil distrik sedang disiapkan.</p>
            @endif

            @if ($settings['economic_potential'])
                <h2 class="mt-12 border-t border-line-strong pt-4 text-card-title font-semibold text-ink">Potensi ekonomi</h2>
                <div class="prose-public mt-4">{!! Format::markdown($settings['economic_potential']) !!}</div>
            @endif
        </article>

        <aside class="h-fit rounded-card border border-line bg-paper p-4 md:p-5" aria-label="Sekilas">
            <h2 class="text-ui font-semibold text-ink">Sekilas</h2>
            @include('publik._stats', ['class' => 'mt-3 grid-cols-3! lg:grid-cols-1! gap-3'])
            @if ($sectors->isNotEmpty())
                <ul class="mt-5 space-y-1.5 border-t border-line pt-4 text-ui">
                    @foreach ($sectors as $sector)
                        <li class="flex justify-between gap-3">
                            <a href="{{ route('public.directory', ['bidang' => $sector->id]) }}" class="rounded-control text-brand hover:text-brand-hover">{{ $sector->name }}</a>
                            <span class="text-ink-soft tabular-nums">{{ Format::number($sector->organizations_count) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('public.directory') }}" class="mt-5 inline-block rounded-control text-ui text-brand hover:text-brand-hover">Buka direktori kelompok</a>
        </aside>
    </div>
</x-layouts.public>
