@use('App\Support\Format')

{{--
    Kontak: banner forest ringkas (sama dengan Direktori/Produk) → kartu kontak cepat yang menumpang di tepi bawah banner
    (Telepon, WhatsApp, Email; hanya yang diisi) → Kantor distrik (alamat, jam layanan, Google Maps, foto kantor) →
    OPD pembina (daftar bergaya indeks seperti Kampung di Profil Ransiki) → panel cream "Ada keperluan?" (arah tautan).
    Semua isian dari Konten Website; kosong = tidak ditampilkan. Kontras di cream: ink 14,0, ink-soft 7,0, clay 7,1.
--}}
@php
    $phone = $settings['office_phone'];
    $quick = array_filter([
        ['phone', 'Telepon', Format::phone($phone), Format::telUrl($phone), false],
        ['chat-bubble-left-right', 'WhatsApp', Format::phone($settings['office_whatsapp']), Format::whatsappUrl($settings['office_whatsapp']), true],
        ['envelope', 'Email', $settings['office_email'], $settings['office_email'] ? 'mailto:'.$settings['office_email'] : null, false],
    ], fn ($item) => filled($item[2]) && $item[3]);
    $office = array_filter([
        ['map-pin', 'Alamat', $settings['office_address']],
        ['clock', 'Jam layanan', $settings['office_hours']],
    ], fn ($item) => filled($item[2]));
    $needs = [
        ['Mendaftarkan kelompok usaha', 'Pendaftaran dilakukan melalui Kantor Distrik Ransiki. Datang langsung atau hubungi kontak kantor.', '#kantor'],
        ['Mencari kelompok atau produk', 'Lihat profil kelompok, produknya, dan kontak yang dibuka kelompok di direktori.', route('public.directory')],
        ['Lupa kata sandi akun', 'Akun yang punya email bisa mengatur ulang sendiri. Akun tanpa email dibantu Admin distrik.', route('password.request')],
        ['Pembinaan usaha', 'Lihat organisasi perangkat daerah yang membina kelompok usaha di Ransiki.', '#opd'],
    ];
    $sectionClass = 'scroll-mt-24 focus:outline-none';
    $headingClass = 'font-serif text-display-sm font-semibold tracking-[-0.01em] text-balance text-ink md:text-title-lg';
@endphp

<x-layouts.public title="Kontak" description="Kontak Kantor Distrik Ransiki: alamat, jam layanan, telepon, WhatsApp, email, dan daftar OPD pembina kelompok usaha.">
    {{-- Banner: 180px HP, 240px desktop; ruang bawah lebih lega bila kartu kontak cepat menumpang di tepinya. --}}
    <section class="on-dark bg-forest text-paper">
        <div @class(['mx-auto flex min-h-45 max-w-public flex-col justify-end px-4 pt-10 md:min-h-60 md:px-6', 'pb-20 md:pb-24' => $quick, 'pb-10 md:pb-12' => ! $quick]) data-intro>
            <nav aria-label="Remah roti" class="mb-3 text-caption text-forest-soft">
                <ol class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    <li><a href="{{ route('home') }}" class="rounded-control text-paper underline-offset-4 hover:underline">Beranda</a></li>
                    <li class="flex items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" /><span aria-current="page">Kontak</span></li>
                </ol>
            </nav>
            <h1 class="font-serif text-display-sm font-semibold tracking-[-0.01em] md:text-title-lg">Hubungi kami</h1>
            <p class="mt-2 max-w-[40rem] text-body text-paper">Kantor Distrik Ransiki dan OPD yang membina kelompok usaha.</p>
        </div>
    </section>

    @if ($quick)
        {{-- Kontak cepat: satu kartu putih menumpang 48px di atas banner; bertumpuk sampai 1024px (nomor tidak terpotong), lalu sebaris. --}}
        <div class="relative z-10 mx-auto -mt-12 max-w-public px-4 md:-mt-14 md:px-6">
            <ul data-intro-search @class(['grid overflow-hidden rounded-tile border border-line bg-paper shadow-strip max-lg:divide-y max-lg:divide-line lg:divide-x lg:divide-line', 'lg:grid-cols-2' => count($quick) === 2, 'lg:grid-cols-3' => count($quick) === 3]) aria-label="Kontak cepat">
                @foreach ($quick as [$icon, $label, $value, $href, $external])
                    <li class="min-w-0">
                        <a href="{{ $href }}" @if ($external) rel="noopener" target="_blank" @endif class="group flex h-full min-h-18 items-center gap-4 px-4 py-4 transition-colors hover:bg-subtle focus-visible:-outline-offset-2 md:px-6 md:py-5">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-cream text-clay" aria-hidden="true">
                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-caption text-ink-soft">{{ $label }}</span>
                                <span class="mt-0.5 block truncate text-body font-semibold text-ink group-hover:text-brand" title="{{ $value }}">{{ $value }}</span>
                            </span>
                            <x-heroicon-o-arrow-up-right class="size-4 shrink-0 text-brand transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transition-none" aria-hidden="true" />
                            @if ($external)<span class="sr-only">(membuka tab baru)</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Kantor distrik: teks 5 kolom (alamat, jam layanan, Google Maps), foto kantor 7 kolom; tanpa foto: panel ikon gedung. --}}
    <section id="kantor" tabindex="-1" class="{{ $sectionClass }}" aria-labelledby="kantor-judul">
        <div class="mx-auto grid max-w-public items-center gap-10 px-4 py-section md:px-6 lg:grid-cols-12 lg:gap-14" x-data>
            <div class="min-w-0 lg:col-span-5" x-reveal="fade-right">
                <p class="text-caption font-semibold tracking-wider text-brand uppercase">Kantor distrik</p>
                <h2 id="kantor-judul" class="mt-3 {{ $headingClass }}">Kantor Distrik Ransiki</h2>
                @if ($office)
                    <dl class="mt-8 border-t border-line">
                        @foreach ($office as [$icon, $label, $value])
                            <div class="flex gap-4 border-b border-line py-5">
                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="mt-0.5 size-5 shrink-0 text-clay" aria-hidden="true" />
                                <div class="min-w-0">
                                    <dt class="text-caption text-ink-soft">{{ $label }}</dt>
                                    <dd class="mt-1 text-body leading-7 break-words text-ink">{!! nl2br(e($value)) !!}</dd>
                                </div>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <p class="mt-4 text-body text-ink-soft">Alamat dan jam layanan kantor sedang disiapkan.</p>
                @endif
                @if ($mapsUrl)
                    <x-public.button :href="$mapsUrl" variant="outline-brand" icon="map" target="_blank" rel="noopener" class="mt-8">
                        Buka di Google Maps<span class="sr-only"> (membuka tab baru)</span>
                    </x-public.button>
                @endif
            </div>

            <div class="min-w-0 lg:col-span-7" x-reveal="fade-left">
                @if ($officePhoto)
                    <x-public.picture :image="$officePhoto" alt="Kantor Distrik Ransiki" sizes="(min-width: 67.5rem) 38rem, (min-width: 64rem) 55vw, 100vw" width="960" height="720" frame="block overflow-hidden rounded-[20px] bg-subtle" class="aspect-[4/3] w-full object-cover" />
                @else
                    <div class="flex aspect-[4/3] items-center justify-center rounded-[20px] bg-cream max-lg:hidden" aria-hidden="true" data-office-illustration>
                        <span class="flex size-28 items-center justify-center rounded-full bg-paper/70 text-clay">
                            <x-heroicon-o-building-office-2 class="size-14 stroke-1" />
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{--
        OPD pembina: kiri judul + ringkasan (menempel mulai 1024px), kanan daftar bernomor bergaya indeks seperti Kampung di
        Profil Ransiki (nama serif, singkatan sebagai pil, keterangan). Baris muncul bergiliran.
    --}}
    <section id="opd" tabindex="-1" class="{{ $sectionClass }} border-y border-line bg-paper" aria-labelledby="opd-judul">
        <div class="mx-auto max-w-public px-4 py-section md:px-6 lg:grid lg:grid-cols-12 lg:gap-12" x-data>
            <div class="lg:col-span-4">
                <div class="lg:sticky lg:top-28" x-reveal="fade-up">
                    <h2 id="opd-judul" class="{{ $headingClass }}">OPD pembina</h2>
                    <p class="mt-4 text-body text-ink-soft">Organisasi perangkat daerah yang membina dan mendampingi kelompok usaha di Distrik Ransiki.</p>
                    @if ($opds->isNotEmpty())
                        <p class="mt-4 text-ui text-ink-soft"><span class="font-semibold text-ink tabular-nums">{{ Format::number($opds->count()) }}</span> OPD</p>
                    @endif
                </div>
            </div>

            <div class="min-w-0 lg:col-span-8">
                @if ($opds->isEmpty())
                    <p class="mt-section-head text-body text-ink-soft lg:mt-0">Daftar OPD sedang disiapkan.</p>
                @else
                    <ol class="mt-section-head border-t border-line lg:mt-0" x-reveal.stagger="fade-up">
                        @foreach ($opds as $opd)
                            <li class="grid grid-cols-[2rem_minmax(0,1fr)] gap-x-3 border-b border-line px-2 py-5 lg:grid-cols-[2.5rem_minmax(0,1fr)] lg:gap-x-4 lg:px-3 lg:py-6">
                                <span class="pt-1 font-serif text-ui text-ink-muted tabular-nums" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                        <h3 class="font-serif text-title-sm font-semibold text-ink">{{ $opd->name }}</h3>
                                        @if ($opd->short_name && $opd->short_name !== $opd->name)
                                            <x-public.pill>{{ $opd->short_name }}</x-public.pill>
                                        @endif
                                    </div>
                                    @if ($opd->description)
                                        <p class="mt-2 max-w-[60ch] text-ui leading-6 text-ink-soft">{{ $opd->description }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>
    </section>

    {{-- Ada keperluan?: panel cream seperti ajakan di Beranda; baris bernomor yang menaut ke tempat yang tepat. --}}
    <section class="mx-auto max-w-public px-4 py-section md:px-6" aria-labelledby="keperluan">
        <div class="grid gap-8 rounded-panel bg-cream px-6 py-section md:px-10 lg:grid-cols-12 lg:gap-16 lg:px-14" x-data>
            <div class="lg:col-span-5" x-reveal="fade-right">
                <h2 id="keperluan" class="font-serif text-display-sm font-semibold text-ink md:text-display">Ada keperluan?</h2>
                <p class="mt-3 max-w-[28rem] text-body leading-7 text-ink-soft">Pilih keperluan Anda agar langsung menuju tempat yang tepat.</p>
            </div>

            <ol class="lg:col-span-7" x-reveal.stagger="fade-up">
                @foreach ($needs as [$title, $text, $href])
                    <li class="border-t border-ink/10 first:border-t-0">
                        <a href="{{ $href }}" class="group -mx-3 grid grid-cols-[3rem_minmax(0,1fr)_1.25rem] items-start gap-3 rounded-tile px-3 py-5 transition-colors hover:bg-paper/60 focus-visible:bg-paper/60 md:grid-cols-[3.5rem_minmax(0,1fr)_1.25rem] md:gap-4">
                            <span class="font-serif text-display-sm font-semibold text-clay tabular-nums" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>
                            <span class="min-w-0">
                                <span class="block text-card-title font-semibold text-ink group-hover:text-brand">{{ $title }}</span>
                                <span class="mt-1 block text-ui text-ink-soft">{{ $text }}</span>
                            </span>
                            <x-heroicon-o-arrow-right class="mt-1.5 size-5 text-brand transition-transform group-hover:translate-x-1 group-focus-visible:translate-x-1 motion-reduce:transition-none" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
</x-layouts.public>
