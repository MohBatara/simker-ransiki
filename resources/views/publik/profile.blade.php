@use('App\Support\Format')

{{--
    Profil Ransiki: banner foto → navigasi dalam halaman (menempel di bawah header) → Sekilas (pembuka + isi profil,
    kartu Data singkat, angka ringkas) → Potensi ekonomi (3 blok selang seling) → Kampung → Kantor distrik.
    Semua teks dan foto dari Konten Website > Profil Ransiki; isian kosong = baris tidak ditampilkan.
    Kontras di cream: ink 14,0, ink-soft 7,0, clay 7,1 (ink-muted hanya 4,4, jadi tidak dipakai di atas cream).
--}}
@php
    $sections = ['sekilas' => 'Sekilas', 'potensi' => 'Potensi ekonomi', 'kampung' => 'Kampung', 'kantor' => 'Kantor distrik'];
    $lead = $text('profile_lead');
    $office = [
        ['map-pin', 'Alamat', $settings['office_address'], null],
        ['clock', 'Jam layanan', $settings['office_hours'], null],
        ['phone', 'Telepon', $settings['office_phone'], $settings['office_phone'] ? 'tel:'.preg_replace('/[^0-9+]/', '', $settings['office_phone']) : null],
        ['envelope', 'Email', $settings['office_email'], $settings['office_email'] ? 'mailto:'.$settings['office_email'] : null],
    ];
    $office = array_filter($office, fn ($item) => filled($item[2]));
    $sectionClass = 'scroll-mt-28 focus:outline-none md:scroll-mt-30';
@endphp

<x-layouts.public
    title="Profil Distrik Ransiki"
    :description="$settings['profile_intro'] ? Str::limit($settings['profile_intro'], 160) : (Format::excerpt($settings['profile_body'], 160) ?? $lead)"
    :image="$banner['fallback']"
>
    {{-- Banner: foto (banner → sampul → bawaan), forest dari bawah; teks muncul naik saat dibuka (data-intro). --}}
    <section class="on-dark relative isolate flex min-h-70 overflow-hidden bg-forest text-paper md:min-h-90 lg:min-h-105" aria-labelledby="judul-profil">
        <x-public.picture :image="$banner" alt="" eager sizes="100vw" width="1600" height="686" frame="absolute inset-0 -z-20 block size-full" class="size-full object-cover" />
        <div class="overlay-band-bottom absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="mx-auto flex w-full max-w-public flex-col justify-end px-4 pt-12 pb-8 md:px-6 md:pb-12 lg:pb-14" data-intro>
            <nav aria-label="Remah roti" class="mb-4 text-caption text-paper">
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    <li><a href="{{ route('home') }}" class="rounded-control underline-offset-4 hover:underline">Beranda</a></li>
                    <li class="flex items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" /><span aria-current="page">Profil Ransiki</span></li>
                </ol>
            </nav>
            <h1 id="judul-profil" class="max-w-3xl font-serif text-hero-sm font-semibold text-balance md:text-hero">Profil Distrik Ransiki</h1>
            @if ($lead)
                <p class="mt-3 max-w-[38rem] text-body text-paper md:text-card-title">{{ $lead }}</p>
            @endif
        </div>
    </section>

    {{-- Navigasi dalam halaman: menempel tepat di bawah bilah header yang sudah menyusut (52/60px). --}}
    <nav aria-label="Bagian halaman" x-data="sectionNav" class="sticky top-13 z-30 border-b border-line bg-paper md:top-15 print:hidden">
        <div class="mx-auto max-w-public md:px-6">
            <ul x-ref="track" class="scrollbar-none relative flex gap-2 overflow-x-auto px-4 md:gap-8 md:px-0">
                @foreach ($sections as $id => $label)
                    <li class="shrink-0">
                        <a
                            href="#{{ $id }}"
                            x-on:click="go($event)"
                            @class([
                                'flex h-12 items-center border-b-2 px-2 text-ui whitespace-nowrap transition-colors md:px-0',
                                'border-brand font-semibold text-brand' => $loop->first,
                                'border-transparent text-ink-soft hover:text-ink' => ! $loop->first,
                            ])
                            x-bind:class="{ 'border-brand font-semibold text-brand': active === '{{ $id }}', 'border-transparent text-ink-soft hover:text-ink': active !== '{{ $id }}' }"
                            x-bind:aria-current="active === '{{ $id }}' && 'location'"
                        >{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>

    {{-- Sekilas: pembuka serif + isi profil (7 kolom), kartu Data singkat (5 kolom), lalu angka ringkas. --}}
    <section id="sekilas" tabindex="-1" class="{{ $sectionClass }}" aria-labelledby="sekilas-judul">
        <div class="mx-auto max-w-public px-4 py-section md:px-6" x-data>
            <div class="grid gap-10 lg:grid-cols-12 lg:gap-x-12">
                <div class="min-w-0 lg:col-span-7" x-reveal="fade-up">
                    <h2 id="sekilas-judul" class="text-caption font-semibold tracking-wider text-brand uppercase">Sekilas</h2>
                    @if ($settings['profile_intro'])
                        <p class="mt-4 max-w-[34em] font-serif text-page-title text-ink md:text-headline">{{ $settings['profile_intro'] }}</p>
                    @endif
                    @if ($settings['profile_body'])
                        <div class="prose-public mt-6 max-w-[68ch] leading-7 text-ink-soft">{!! Format::markdown($settings['profile_body']) !!}</div>
                    @elseif (! $settings['profile_intro'])
                        <p class="mt-4 text-body text-ink-soft">Profil distrik sedang disiapkan.</p>
                    @endif
                </div>

                @if ($facts->isNotEmpty())
                    <aside class="h-fit rounded-[20px] bg-cream p-6 md:p-8 lg:col-span-5" aria-labelledby="data-singkat" x-reveal="fade-left">
                        <h3 id="data-singkat" class="font-serif text-card-title font-semibold text-ink">Data singkat</h3>
                        <dl class="mt-4 divide-y divide-sand/70">
                            @foreach ($facts as $label => $value)
                                <div class="flex items-baseline justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                    <dt class="text-ui text-ink-soft">{{ $label }}</dt>
                                    <dd class="text-right text-ui font-semibold text-ink tabular-nums">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($settings['profile_data_source'])
                            <p class="mt-5 text-caption text-ink-soft">Sumber data: {{ $settings['profile_data_source'] }}</p>
                        @endif
                    </aside>
                @endif
            </div>

            @include('publik._stats', ['class' => 'mt-section-head'])
        </div>
    </section>

    {{-- Potensi ekonomi: blok foto 4:3 dan teks selang seling (HP foto di atas). --}}
    <section id="potensi" tabindex="-1" class="{{ $sectionClass }} border-y border-line bg-paper" aria-labelledby="potensi-judul">
        <div class="mx-auto max-w-public px-4 py-section md:px-6" x-data>
            <div class="max-w-[42rem]">
                @include('publik._section-heading', ['title' => 'Potensi ekonomi', 'id' => 'potensi-judul'])
                @if ($text('potential_intro'))
                    <p class="mt-3 text-body text-ink-soft">{{ $text('potential_intro') }}</p>
                @endif
            </div>

            <div class="mt-section-head space-y-14 md:space-y-20">
                @foreach ($potentials as $block)
                    <article class="grid items-center gap-6 md:grid-cols-2 md:gap-10 lg:gap-16" x-reveal="{{ $loop->odd ? 'fade-right' : 'fade-left' }}" aria-labelledby="potensi-{{ $block['n'] }}">
                        <x-public.picture
                            :image="$block['photo']"
                            alt=""
                            sizes="(min-width: 67.5rem) 32rem, (min-width: 48rem) 46vw, 100vw"
                            width="960"
                            height="720"
                            frame="{{ 'block overflow-hidden rounded-[20px] bg-subtle'.($loop->even ? ' md:order-last' : '') }}"
                            class="aspect-[4/3] w-full object-cover"
                        />
                        <div class="min-w-0">
                            @if ($block['sector'])
                                <p class="text-caption font-semibold tracking-wider text-clay uppercase">{{ $block['sector'] }}</p>
                            @endif
                            <h3 id="potensi-{{ $block['n'] }}" class="mt-2 font-serif text-display-sm font-semibold text-ink">{{ $block['title'] }}</h3>
                            <p class="mt-3 max-w-[34rem] text-body leading-7 text-ink-soft">{{ $block['text'] }}</p>
                            <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-1">
                                @if ($block['url'])
                                    <p class="text-ui text-ink-soft"><span class="font-semibold text-ink tabular-nums">{{ Format::number($block['count']) }}</span> kelompok</p>
                                    <a href="{{ $block['url'] }}" class="group inline-flex min-h-11 items-center gap-2 rounded-control text-ui font-semibold text-brand underline-offset-4 hover:underline">
                                        Lihat kelompok<span class="sr-only"> bidang {{ $block['sector'] }}</span>
                                        <x-heroicon-o-arrow-right class="size-4 transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none" aria-hidden="true" />
                                    </a>
                                @else
                                    <p class="text-ui text-ink-soft">Belum ada kelompok terdaftar</p>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Kampung: semua kampung Data Master urut abjad; kartu menaut ke direktori tersaring bila ada kelompok publik. --}}
    <section id="kampung" tabindex="-1" class="{{ $sectionClass }}" aria-labelledby="kampung-judul">
        <div class="mx-auto max-w-public px-4 py-section md:px-6" x-data>
            <div class="max-w-[42rem]">
                @include('publik._section-heading', ['title' => 'Kampung di Ransiki', 'id' => 'kampung-judul'])
                <p class="mt-3 text-body text-ink-soft">Pilih kampung untuk melihat kelompok yang terdaftar di sana.</p>
            </div>

            @if ($villages->isEmpty())
                <p class="mt-section-head text-body text-ink-soft">Daftar kampung sedang disiapkan.</p>
            @else
                <ul class="mt-section-head grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4 lg:grid-cols-4" x-reveal.stagger="fade-up">
                    @foreach ($villages as $village)
                        <li class="min-w-0">
                            @if ($village->organizations_count > 0)
                                <a href="{{ route('public.directory', ['kampung' => $village->id]) }}" class="group flex h-full min-h-24 flex-col justify-between gap-3 rounded-public border border-line bg-paper p-4 shadow-card transition-colors hover:border-brand md:p-5">
                                    <span class="font-serif text-card-title font-semibold text-ink">{{ $village->name }}</span>
                                    <span class="flex items-center justify-between gap-2 text-ui text-ink-muted">
                                        <span class="tabular-nums">{{ Format::number($village->organizations_count) }} kelompok</span>
                                        <x-heroicon-o-arrow-right class="size-4 shrink-0 text-brand transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none" aria-hidden="true" />
                                    </span>
                                </a>
                            @else
                                <div class="flex h-full min-h-24 flex-col justify-between gap-3 rounded-public border border-line p-4 md:p-5">
                                    <span class="font-serif text-card-title font-semibold text-ink">{{ $village->name }}</span>
                                    <span class="text-ui text-ink-muted">Belum ada kelompok terdaftar</span>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- Kantor distrik: panel cream dua kolom; kanan foto kantor atau gambar ikon gedung bila belum ada. --}}
    <section id="kantor" tabindex="-1" class="{{ $sectionClass }}" aria-labelledby="kantor-judul">
        <div class="mx-auto max-w-public px-4 pb-section md:px-6" x-data>
            <div class="grid items-center gap-8 rounded-panel bg-cream p-6 md:p-10 lg:grid-cols-2 lg:gap-14 lg:p-14" x-reveal="fade-up">
                <div class="min-w-0">
                    <h2 id="kantor-judul" class="font-serif text-display-sm font-semibold text-ink md:text-display">Kantor Distrik Ransiki</h2>
                    @if ($office)
                        <dl class="mt-6 space-y-4">
                            @foreach ($office as [$icon, $label, $value, $href])
                                <div class="flex gap-3">
                                    <x-dynamic-component :component="'heroicon-o-'.$icon" class="mt-0.5 size-5 shrink-0 text-clay" aria-hidden="true" />
                                    <div class="min-w-0">
                                        <dt class="text-caption text-ink-soft">{{ $label }}</dt>
                                        <dd class="mt-0.5 text-body break-words text-ink">
                                            @if ($href)
                                                <a href="{{ $href }}" class="rounded-control underline-offset-4 hover:text-brand hover:underline">{{ $value }}</a>
                                            @else
                                                {!! nl2br(e($value)) !!}
                                            @endif
                                        </dd>
                                    </div>
                                </div>
                            @endforeach
                        </dl>
                    @else
                        <p class="mt-4 text-body text-ink-soft">Informasi kantor sedang disiapkan.</p>
                    @endif
                    <div class="mt-8 flex flex-wrap gap-3">
                        <x-public.pill-link :href="route('public.contact')">Hubungi kami</x-public.pill-link>
                        @if ($settings['office_phone'])
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['office_phone']) }}" class="inline-flex min-h-11 items-center gap-2 rounded-full border border-forest px-5 font-semibold text-forest transition-colors hover:bg-paper md:hidden">
                                <x-heroicon-o-phone class="size-5" aria-hidden="true" />
                                Telepon
                            </a>
                        @endif
                    </div>
                </div>

                @if ($officePhoto)
                    <x-public.picture :image="$officePhoto" alt="Kantor Distrik Ransiki" sizes="(min-width: 64rem) 30rem, 100vw" width="960" height="720" frame="block overflow-hidden rounded-[20px]" class="aspect-[4/3] w-full object-cover" />
                @else
                    <div class="flex aspect-[4/3] items-center justify-center rounded-[20px] border border-sand/70 bg-paper/60 max-lg:hidden" aria-hidden="true" data-office-illustration>
                        <span class="flex size-28 items-center justify-center rounded-full bg-cream text-clay">
                            <x-heroicon-o-building-office-2 class="size-14 stroke-1" />
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </section>
</x-layouts.public>
