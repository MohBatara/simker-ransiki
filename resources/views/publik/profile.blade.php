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
    $sectionClass = 'scroll-mt-32 focus:outline-none';
    // Judul bagian: 28px HP, 40px mulai 768px, sedikit dirapatkan.
    $headingClass = 'font-serif text-display-sm font-semibold tracking-[-0.01em] text-balance text-ink md:text-title-lg';
@endphp

<x-layouts.public
    title="Profil Distrik Ransiki"
    :description="$settings['profile_intro'] ? Str::limit($settings['profile_intro'], 160) : (Format::excerpt($settings['profile_body'], 160) ?? $lead)"
    :image="$banner['fallback']"
>
    {{--
        Banner: foto (banner → sampul → bawaan) dengan zoom pelan saat dibuka, forest dari bawah; teks rata kiri bawah,
        muncul naik (data-intro). Tinggi 320px HP, 400px tablet, 70vh (maks 640px) desktop.
    --}}
    <section class="on-dark relative isolate flex min-h-80 overflow-hidden bg-forest text-paper md:min-h-100 lg:min-h-[min(70vh,40rem)]" aria-labelledby="judul-profil">
        <x-public.picture :image="$banner" alt="" eager sizes="100vw" width="1600" height="686" frame="absolute inset-0 -z-20 block size-full" class="banner-zoom size-full object-cover" />
        <div class="overlay-band-bottom absolute inset-0 -z-10" aria-hidden="true"></div>
        <div class="mx-auto flex w-full max-w-public flex-col justify-end px-4 pt-16 pb-10 md:px-6 md:pb-16 lg:pb-20" data-intro>
            <nav aria-label="Remah roti" class="mb-5 text-caption text-paper">
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    <li><a href="{{ route('home') }}" class="rounded-control underline-offset-4 hover:underline">Beranda</a></li>
                    <li class="flex items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" /><span aria-current="page">Profil Ransiki</span></li>
                </ol>
            </nav>
            <h1 id="judul-profil" class="max-w-3xl font-serif text-hero-sm font-semibold tracking-[-0.01em] text-balance md:text-hero lg:text-hero-lg">Profil Distrik Ransiki</h1>
            @if ($lead)
                <p class="mt-5 max-w-[38rem] text-body text-paper md:text-page-title">{{ $lead }}</p>
            @endif
        </div>
    </section>

    {{--
        Navigasi dalam halaman: menempel tepat di bawah bilah header yang sudah menyusut (52/60px). Pil; aktif = pil brand
        berteks putih. Latar putih semi transparan + blur (pengecualian Bagian 7); tanpa dukungan blur, putih hampir padat.
    --}}
    <nav aria-label="Bagian halaman" x-data="sectionNav" class="sticky top-13 z-30 border-b border-line bg-paper/95 md:top-15 print:hidden supports-[backdrop-filter]:bg-paper/75 supports-[backdrop-filter]:backdrop-blur-[12px]">
        <div class="mx-auto max-w-public md:px-6">
            <ul x-ref="track" class="scrollbar-none relative flex gap-1 overflow-x-auto px-4 py-2 md:gap-2 md:px-0">
                @foreach ($sections as $id => $label)
                    <li class="shrink-0">
                        <a
                            href="#{{ $id }}"
                            x-on:click="go($event)"
                            @class([
                                'flex h-11 items-center rounded-full px-4 text-ui font-semibold whitespace-nowrap transition-colors md:h-9',
                                'bg-brand text-paper' => $loop->first,
                                'text-ink-soft hover:bg-subtle hover:text-ink' => ! $loop->first,
                            ])
                            x-bind:class="{ 'bg-brand text-paper': active === '{{ $id }}', 'text-ink-soft hover:bg-subtle hover:text-ink': active !== '{{ $id }}' }"
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
            <div class="grid gap-12 lg:grid-cols-12 lg:gap-x-12">
                <div class="min-w-0 lg:col-span-7" x-reveal="fade-up">
                    <h2 id="sekilas-judul" class="text-caption font-semibold tracking-wider text-brand uppercase">Sekilas</h2>
                    @if ($settings['profile_intro'])
                        <p class="mt-5 max-w-[34em] font-serif text-page-title leading-[1.45] text-ink">{{ $settings['profile_intro'] }}</p>
                    @endif
                    @if ($settings['profile_body'])
                        <div class="prose-public mt-8 max-w-[68ch] leading-7 text-ink-soft">{!! Format::markdown($settings['profile_body']) !!}</div>
                    @elseif (! $settings['profile_intro'])
                        <p class="mt-4 text-body text-ink-soft">Profil distrik sedang disiapkan.</p>
                    @endif
                </div>

                @if ($facts->isNotEmpty())
                    {{-- Bento: ubin cream 2 kolom (ubin terakhir selebar grid bila jumlahnya ganjil); Jumlah kampung = ubin forest. --}}
                    <aside class="h-fit lg:col-span-5" aria-labelledby="data-singkat" x-reveal="fade-left">
                        <h3 id="data-singkat" class="font-serif text-card-title font-semibold text-ink">Data singkat</h3>
                        <dl class="mt-4 grid grid-cols-2 gap-3">
                            @foreach ($facts as $label => $value)
                                @php $accent = $label === 'Jumlah kampung'; @endphp
                                <div @class(['flex min-w-0 flex-col gap-1 rounded-tile p-4 odd:last:col-span-2 md:p-5', 'bg-forest text-paper' => $accent, 'bg-cream' => ! $accent])>
                                    <dt @class(['text-caption', 'text-forest-soft' => $accent, 'text-ink-soft' => ! $accent])>{{ $label }}</dt>
                                    <dd @class(['font-serif text-headline font-semibold break-words tabular-nums', 'text-paper' => $accent, 'text-ink' => ! $accent])>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if ($settings['profile_data_source'])
                            <p class="mt-4 text-caption text-ink-muted">Sumber data: {{ $settings['profile_data_source'] }}</p>
                        @endif
                    </aside>
                @endif
            </div>

            @include('publik._stats', ['class' => 'mt-section-head'])
        </div>
    </section>

    {{--
        Potensi ekonomi: foto 5:4 (7 kolom) dan kartu teks putih berbayangan halus (5 kolom) yang menumpuk 32px ke foto,
        selang seling kiri kanan. HP: kartu menumpuk 32px ke bawah foto.
    --}}
    <section id="potensi" tabindex="-1" class="{{ $sectionClass }} border-t border-line" aria-labelledby="potensi-judul">
        <div class="mx-auto max-w-public px-4 py-section md:px-6" x-data>
            <div class="max-w-[42rem]">
                <h2 id="potensi-judul" class="{{ $headingClass }}">Potensi ekonomi</h2>
                @if ($text('potential_intro'))
                    <p class="mt-4 text-body text-ink-soft">{{ $text('potential_intro') }}</p>
                @endif
            </div>

            <div class="mt-section-head space-y-14 md:space-y-20">
                @foreach ($potentials as $block)
                    <article class="md:grid md:grid-cols-12 md:items-center" x-reveal="{{ $loop->odd ? 'fade-right' : 'fade-left' }}" aria-labelledby="potensi-{{ $block['n'] }}">
                        <x-public.picture
                            :image="$block['photo']"
                            alt=""
                            sizes="(min-width: 67.5rem) 38rem, (min-width: 48rem) 58vw, 100vw"
                            width="960"
                            height="768"
                            frame="{{ 'block overflow-hidden rounded-panel bg-subtle md:col-span-7 md:row-start-1 '.($loop->odd ? 'md:col-start-1' : 'md:col-start-6') }}"
                            class="aspect-[5/4] w-full object-cover"
                        />
                        <div class="{{ 'relative z-10 mx-4 -mt-8 min-w-0 rounded-[20px] border border-line bg-paper p-6 shadow-card md:mx-0 md:mt-0 md:col-span-5 md:row-start-1 md:p-8 lg:p-10 '.($loop->odd ? 'md:col-start-8 md:-ml-8' : 'md:col-start-1 md:-mr-8') }}">
                            @if ($block['sector'])
                                <p class="text-caption font-semibold tracking-wider text-clay uppercase">{{ $block['sector'] }}</p>
                            @endif
                            <h3 id="potensi-{{ $block['n'] }}" class="mt-3 font-serif text-display-sm font-semibold tracking-[-0.01em] text-ink">{{ $block['title'] }}</h3>
                            <p class="mt-4 max-w-[34rem] text-body leading-7 text-ink-soft">{{ $block['text'] }}</p>
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

    {{--
        Kampung: daftar bergaya indeks, urut jumlah kelompok publik terbanyak (sama: abjad). Desktop grid 4 + 8: kiri judul +
        ringkasan (menempel), kanan baris [nomor, nama tanpa awalan "Kampung", batang sebanding kampung terbanyak, jumlah,
        maks 3 ikon bidang, panah]; seluruh baris menaut ke direktori tersaring. < 1024px: ringkasan sebaris di atas, batang
        selebar baris di baris kedua. Batang tumbuh dari kiri saat baris muncul (.village-bar, app.css). Kampung tanpa
        kelompok publik di akhir, tanpa batang dan tanpa tautan. Kontras: nomor ink-muted di latar hover cream/50 4,8:1.
    --}}
    @php
        $villageName = fn (string $name) => preg_replace('/^kampung\s+/iu', '', $name);
        $maxVillage = max(1, (int) $villages->max('organizations_count'));
        $listedTotal = (int) $villages->sum('organizations_count');
        $mostActive = $villages->first(fn ($village) => $village->organizations_count > 0);
        $villageSummary = array_filter([
            Format::number($villages->count()).' kampung',
            Format::number($listedTotal).' kelompok terdaftar',
            $mostActive ? 'Paling aktif: '.$villageName($mostActive->name).' ('.Format::number($mostActive->organizations_count).' kelompok)' : null,
        ]);
    @endphp
    <section id="kampung" tabindex="-1" class="{{ $sectionClass }} border-y border-line bg-paper" aria-labelledby="kampung-judul">
        <div class="mx-auto max-w-public px-4 py-section md:px-6 lg:grid lg:grid-cols-12 lg:gap-12" x-data>
            <div class="lg:col-span-4">
                <div class="lg:sticky lg:top-36">
                    <h2 id="kampung-judul" class="{{ $headingClass }}">Kampung di Ransiki</h2>
                    <p class="mt-4 text-body text-ink-soft">Urut dari kampung dengan kelompok terdaftar terbanyak. Pilih kampung untuk melihat kelompoknya di direktori.</p>
                    @if ($villages->isNotEmpty())
                        {{-- Ringkasan: sebaris kecil di HP/tablet, daftar bergaris tipis mulai 1024px. --}}
                        <p class="mt-4 text-ui text-ink-soft lg:hidden">{{ implode(' · ', $villageSummary) }}</p>
                        <ul class="mt-6 hidden border-t border-line text-ui text-ink-soft lg:block">
                            @foreach ($villageSummary as $item)
                                <li class="border-b border-line py-3">{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="min-w-0 lg:col-span-8">
                @if ($villages->isEmpty())
                    <p class="mt-section-head text-body text-ink-soft lg:mt-0">Daftar kampung sedang disiapkan.</p>
                @else
                    <ol class="mt-section-head border-t border-line lg:mt-0" x-reveal.stagger="fade-up">
                        @foreach ($villages as $village)
                            @php
                                $number = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
                                $listed = $village->organizations_count > 0;
                                $rowClass = 'grid grid-cols-[2rem_minmax(0,1fr)_auto_auto] items-center gap-x-3 gap-y-2 px-2 py-3 lg:grid-cols-[2.5rem_minmax(0,12rem)_minmax(0,1fr)_2.5rem_4.5rem_1.25rem] lg:gap-x-4 lg:px-3 lg:py-5';
                            @endphp
                            <li class="border-b border-line">
                                @if ($listed)
                                    <a href="{{ route('public.directory', ['kampung' => $village->id]) }}" class="group {{ $rowClass }} transition-colors hover:bg-cream/50 focus-visible:bg-cream/50">
                                        <span class="col-start-1 row-start-1 font-serif text-ui text-ink-muted tabular-nums" aria-hidden="true">{{ $number }}</span>
                                        <span class="col-start-2 row-start-1 truncate font-serif text-title-sm font-semibold text-ink" title="{{ $village->name }}">{{ $villageName($village->name) }}</span>
                                        <span class="col-span-full row-start-2 h-1.5 overflow-hidden rounded-full bg-line lg:col-span-1 lg:col-start-3 lg:row-start-1" aria-hidden="true">
                                            <span class="village-bar block h-full rounded-full bg-brand group-hover:bg-forest group-focus-visible:bg-forest" style="width: {{ round($village->organizations_count / $maxVillage * 100, 1) }}%"></span>
                                        </span>
                                        <span class="col-start-4 row-start-1 text-right text-body font-semibold text-ink tabular-nums lg:col-start-4">{{ Format::number($village->organizations_count) }}<span class="sr-only"> kelompok</span></span>
                                        <span class="col-start-3 row-start-1 flex items-center justify-end gap-1.5 text-clay lg:col-start-5 lg:justify-start">
                                            @foreach ($village->sectors as $sector)
                                                <span title="{{ $sector }}" role="img" aria-label="{{ $sector }}"><x-public.icon :name="App\Support\PublicIcons::sector($sector)" class="size-[18px]" /></span>
                                            @endforeach
                                        </span>
                                        <x-heroicon-o-arrow-right class="hidden size-5 text-brand transition-transform group-hover:translate-x-1 group-focus-visible:translate-x-1 motion-reduce:transition-none lg:col-start-6 lg:row-start-1 lg:block" aria-hidden="true" />
                                    </a>
                                @else
                                    <div class="{{ $rowClass }}">
                                        <span class="col-start-1 row-start-1 font-serif text-ui text-ink-muted tabular-nums" aria-hidden="true">{{ $number }}</span>
                                        <span class="col-start-2 row-start-1 truncate font-serif text-title-sm font-semibold text-ink" title="{{ $village->name }}">{{ $villageName($village->name) }}</span>
                                        <span class="col-span-full row-start-2 text-ui text-ink-muted lg:col-span-4 lg:col-start-3 lg:row-start-1">Belum ada kelompok terdaftar</span>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </section>

    {{-- Kantor distrik: panel cream dua kolom; kanan foto kantor atau gambar ikon gedung bila belum ada. --}}
    <section id="kantor" tabindex="-1" class="{{ $sectionClass }}" aria-labelledby="kantor-judul">
        <div class="mx-auto max-w-public px-4 py-section md:px-6" x-data>
            <div class="grid items-center gap-8 rounded-panel bg-cream p-6 md:p-10 lg:grid-cols-2 lg:gap-14 lg:p-14" x-reveal="fade-up">
                <div class="min-w-0">
                    <h2 id="kantor-judul" class="{{ $headingClass }}">Kantor Distrik Ransiki</h2>
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
