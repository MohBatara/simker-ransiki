@use('App\Support\Format')

<x-layouts.public title="Profil Distrik Ransiki" :description="Format::excerpt($settings['profile_body'], 160) ?? 'Profil dan potensi ekonomi Distrik Ransiki.'">
    <x-public.band title="Profil Distrik Ransiki" :image="$cover" :crumbs="['Profil Ransiki' => null]">
        @if ($updatedAt)
            <p class="mt-3 text-caption text-paper">Terakhir diperbarui {{ Format::date($updatedAt) }}</p>
        @endif
    </x-public.band>

    <div class="mx-auto grid max-w-public gap-10 px-4 py-section md:px-6 lg:grid-cols-[minmax(0,1fr)_17rem] lg:gap-14">
        <article>
            {{-- Angka ringkas tepat di bawah paragraf pembuka (paragraf pertama Markdown), sisa profil sesudahnya. --}}
            @php
                $profileHtml = (string) Format::markdown($settings['profile_body']);
                $introEnd = $profileHtml !== '' ? strpos($profileHtml, '</p>') : false;
                [$profileIntro, $profileRest] = $introEnd === false ? [$profileHtml, ''] : [substr($profileHtml, 0, $introEnd + 4), substr($profileHtml, $introEnd + 4)];
            @endphp
            @if ($settings['profile_body'])
                <div class="prose-public">{!! $profileIntro !!}</div>
            @else
                <p class="text-body text-ink-soft">Profil distrik sedang disiapkan.</p>
            @endif
            @include('publik._stats', ['class' => 'mt-8'])
            @if (trim($profileRest) !== '')
                <div class="prose-public mt-8">{!! $profileRest !!}</div>
            @endif

            @if ($settings['economic_potential'])
                <h2 class="mt-section font-serif text-display-sm font-semibold text-ink">Potensi ekonomi</h2>
                <div class="prose-public mt-section-head">{!! Format::markdown($settings['economic_potential']) !!}</div>
            @endif
        </article>

        <aside class="on-dark h-fit rounded-public bg-forest p-4 text-paper shadow-card md:p-5" aria-label="Sekilas">
            <h2 class="font-serif text-card-title font-semibold">Sekilas</h2>
            @if ($sectors->isNotEmpty())
                <ul class="mt-4 space-y-1.5 text-ui">
                    @foreach ($sectors as $sector)
                        <li class="flex justify-between gap-3">
                            <a href="{{ route('public.directory', ['bidang' => $sector->id]) }}" class="rounded-control text-paper underline-offset-4 hover:underline">{{ $sector->name }}</a>
                            <span class="text-forest-soft tabular-nums">{{ Format::number($sector->organizations_count) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('public.directory') }}" class="mt-5 inline-flex items-center gap-2 rounded-control text-ui font-semibold text-paper underline-offset-4 hover:underline">Buka direktori kelompok <x-heroicon-o-arrow-right class="size-4" /></a>
        </aside>
    </div>
</x-layouts.public>
