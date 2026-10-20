@use('App\Enums\ProfileStatus')
@use('App\Support\Initials')
@use('App\Support\PublicIcons')

{{--
    Detail produk publik: remah, galeri (kiri 7) + info (kanan 5), kartu kelompok pembuat, tombol hubungi (hanya bila
    kontak kelompok boleh tampil) atau profil kelompok, bagikan/salin tautan, lalu produk lain dari kelompok yang sama
    dan produk serupa (bidang sama). HP: foto di atas, info di bawah; tombol utama menempel di bawah layar setelah tombol
    aslinya tergulir lewat (Alpine stickyCta). JSON-LD Product tanpa harga.
--}}
@php
    $url = route('public.product', $product->slug);
    $profileUrl = route('public.organization', $organization->slug);
    $shareUrl = 'https://wa.me/?text='.rawurlencode($product->name.' dari '.$organization->name.': '.$url);
    $verified = $organization->profile_status === ProfileStatus::Verified;
    $cta = $whatsapp
        ? ['href' => $whatsapp.'?text='.rawurlencode('Halo '.$organization->name.', saya tertarik dengan produk '.$product->name.' yang saya lihat di SIM-KER Distrik Ransiki.'), 'label' => 'Hubungi kelompok', 'external' => true]
        : ['href' => $profileUrl, 'label' => 'Lihat profil kelompok', 'external' => false];
    $facts = [
        'Kapasitas' => $product->capacity,
        'Satuan' => $product->unit,
        'Kampung' => $organization->village->name,
        'Jenis kelembagaan' => $organization->institutionType->name,
    ];
    $buttonSecondary = 'inline-flex min-h-11 items-center gap-2 rounded-full border border-line-strong bg-paper px-4 text-ui font-semibold text-ink transition-colors hover:border-brand hover:text-brand';
@endphp

<x-layouts.public :title="$product->name.' dari '.$organization->name" :description="$description" :image="$ogImage">
    <x-slot:meta>
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
    </x-slot:meta>

<div x-data="stickyCta" class="reveal-quick">
    <div class="mx-auto max-w-public px-4 pt-6 pb-section md:px-6 md:pt-8">
        <nav aria-label="Remah roti" class="text-caption text-ink-soft">
            <ol class="flex min-w-0 items-center gap-x-1.5">
                <li class="shrink-0"><a href="{{ route('home') }}" class="rounded-control text-brand underline-offset-4 hover:underline">Beranda</a></li>
                <li class="flex shrink-0 items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5" aria-hidden="true" /><a href="{{ route('public.products') }}" class="rounded-control text-brand underline-offset-4 hover:underline">Produk</a></li>
                <li class="flex min-w-0 items-center gap-1.5"><x-heroicon-o-chevron-right class="size-3.5 shrink-0" aria-hidden="true" /><span class="truncate" aria-current="page">{{ $product->name }}</span></li>
            </ol>
        </nav>

        <div class="mt-5 grid gap-8 md:mt-6 lg:grid-cols-12 lg:gap-12">
            {{-- Galeri: foto utama 1:1 bersudut 20px, thumbnail bila ada foto lain, tampilan perbesar (dialog). --}}
            <div class="min-w-0 lg:col-span-7" data-intro x-data="productGallery({{ count($photos) }})">
                @if ($photos)
                    <button type="button" x-on:click="open()" class="group relative block aspect-square w-full overflow-hidden rounded-[20px] bg-cream" aria-label="Perbesar foto {{ $product->name }}">
                        @foreach ($photos as $photo)
                            <x-public.picture
                                :image="$photo['image']"
                                :alt="$loop->first ? $product->name : $product->name.', foto '.$loop->iteration"
                                sizes="(min-width: 64rem) 38rem, 100vw"
                                width="800"
                                height="800"
                                :eager="$loop->first"
                                frame="absolute inset-0 block size-full"
                                class="size-full object-cover"
                                x-show="current === {{ $loop->index }}"
                                :style="$loop->first ? null : 'display: none'"
                            />
                        @endforeach
                        <span class="absolute right-4 bottom-4 inline-flex h-9 items-center gap-1.5 rounded-full bg-paper px-3 text-caption font-semibold text-ink shadow-soft transition-colors group-hover:text-brand" aria-hidden="true">
                            <x-heroicon-o-magnifying-glass-plus class="size-4" />Perbesar
                        </span>
                    </button>

                    @if (count($photos) > 1)
                        <ul class="mt-3 grid grid-cols-5 gap-2 md:gap-3" aria-label="Foto lain">
                            @foreach ($photos as $photo)
                                <li>
                                    <button
                                        type="button"
                                        x-on:click="show({{ $loop->index }})"
                                        x-bind:aria-pressed="current === {{ $loop->index }}"
                                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                                        @class(['block aspect-square w-full overflow-hidden rounded-photo bg-cream outline-offset-2 transition-opacity', 'outline-2 outline-brand' => $loop->first])
                                        x-bind:class="{ 'outline-2 outline-brand': current === {{ $loop->index }}, 'opacity-80 hover:opacity-100': current !== {{ $loop->index }} }"
                                    >
                                        <span class="sr-only">Tampilkan foto {{ $loop->iteration }} dari {{ $loop->count }}</span>
                                        <img src="{{ $photo['thumb'] }}" alt="" width="120" height="120" loading="lazy" decoding="async" class="size-full object-cover">
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <dialog
                        x-ref="lightbox"
                        x-on:click="backdrop($event)"
                        x-on:keydown="key($event)"
                        aria-label="Foto {{ $product->name }}"
                        class="m-auto h-dvh max-h-none w-dvw max-w-none bg-transparent p-0 backdrop:bg-ink/90"
                    >
                        <div class="pointer-events-none flex size-full items-center justify-center p-4 md:p-12">
                            @foreach ($photos as $photo)
                                <img src="{{ $photo['full'] }}" alt="{{ $product->name }}, foto {{ $loop->iteration }}" loading="lazy" decoding="async" class="pointer-events-auto max-h-full max-w-full rounded-photo object-contain" x-show="current === {{ $loop->index }}" @unless ($loop->first) style="display: none" @endunless>
                            @endforeach
                        </div>
                        <button type="button" x-on:click="close()" autofocus class="absolute top-3 right-3 flex size-11 items-center justify-center rounded-full bg-paper text-ink hover:text-brand">
                            <span class="sr-only">Tutup</span><x-heroicon-o-x-mark class="size-6" aria-hidden="true" />
                        </button>
                        @if (count($photos) > 1)
                            <button type="button" x-on:click="show(current - 1)" class="absolute top-1/2 left-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-paper text-ink hover:text-brand">
                                <span class="sr-only">Foto sebelumnya</span><x-heroicon-o-chevron-left class="size-6" aria-hidden="true" />
                            </button>
                            <button type="button" x-on:click="show(current + 1)" class="absolute top-1/2 right-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-paper text-ink hover:text-brand">
                                <span class="sr-only">Foto berikutnya</span><x-heroicon-o-chevron-right class="size-6" aria-hidden="true" />
                            </button>
                            <p class="absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full bg-paper px-3 py-1 text-caption font-semibold text-ink tabular-nums" aria-live="polite"><span x-text="current + 1">1</span> dari {{ count($photos) }}</p>
                        @endif
                    </dialog>
                @else
                    <div class="flex aspect-square w-full items-center justify-center rounded-[20px] bg-cream text-clay" aria-hidden="true">
                        <x-public.icon :name="PublicIcons::sector($sector?->name)" class="size-24 stroke-1 md:size-32" />
                    </div>
                @endif
            </div>

            {{-- Info produk. --}}
            <div class="min-w-0 lg:col-span-5" data-intro>
                @if ($sector)
                    <p class="text-caption font-semibold tracking-wider text-clay uppercase">{{ $sector->name }}</p>
                @endif
                <h1 class="mt-2 font-serif text-display-sm font-semibold tracking-[-0.01em] text-ink md:text-hero-sm">{{ $product->name }}</h1>
                @if ($product->is_featured || $inactive)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($product->is_featured)
                            <span class="inline-flex h-7 items-center gap-1 rounded-full bg-sun text-ink px-3 text-caption font-semibold"><x-heroicon-o-star class="size-3.5" aria-hidden="true" />Pilihan distrik</span>
                        @endif
                        @if ($inactive)
                            <x-public.pill>Kelompok tidak aktif sementara</x-public.pill>
                        @endif
                    </div>
                @endif

                <div>
                    @if ($product->description)
                        <p class="mt-5 whitespace-pre-line text-body text-ink-soft">{{ $product->description }}</p>
                    @endif

                    <dl class="mt-6 border-t border-line text-ui">
                        @foreach ($facts as $label => $value)
                            <div class="flex items-baseline justify-between gap-4 border-b border-line py-3">
                                <dt class="text-ink-soft">{{ $label }}</dt>
                                <dd @class(['min-w-0 text-right', 'font-semibold text-ink' => filled($value), 'text-ink-muted' => blank($value)])>{{ filled($value) ? $value : 'Belum diisi' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    {{-- Kelompok pembuat: seluruh kartu menaut ke profil kelompok. --}}
                    <div class="group relative mt-6 flex items-center gap-4 rounded-tile border border-line bg-paper p-4 shadow-card transition-colors hover:border-brand has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-brand">
                        <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-cream font-serif text-card-title font-semibold text-clay" aria-hidden="true">{{ Initials::of($organization->name) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-caption text-ink-muted">Dibuat oleh</p>
                            <p class="font-serif text-card-title font-semibold text-ink">
                                <a href="{{ $profileUrl }}" class="after:absolute after:inset-0 focus-visible:outline-none group-hover:text-brand">{{ $organization->name }}</a>
                            </p>
                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-caption">
                                <span class="inline-flex items-center gap-1 text-ink-soft"><x-heroicon-o-map-pin class="size-4 text-brand" aria-hidden="true" />{{ $organization->village->name }}</span>
                                @if ($verified)
                                    <span class="inline-flex items-center gap-1 font-semibold text-brand"><x-heroicon-o-check-badge class="size-4" aria-hidden="true" />Diverifikasi OPD</span>
                                @endif
                            </div>
                        </div>
                        <x-heroicon-o-arrow-right class="size-5 shrink-0 text-brand transition-transform group-hover:translate-x-1 motion-reduce:transition-none" aria-hidden="true" />
                    </div>

                    <div class="mt-6" x-ref="cta">
                        <x-public.pill-link :href="$cta['href']" full class="sm:w-auto sm:justify-start" :target="$cta['external'] ? '_blank' : null" :rel="$cta['external'] ? 'noopener' : null">{{ $cta['label'] }}</x-public.pill-link>
                        @if ($phone)
                            <p class="mt-3 text-ui text-ink-soft">Atau telepon <a href="{{ \App\Support\Format::telUrl($phone) }}" class="rounded-control font-semibold text-brand underline underline-offset-4 hover:text-brand-hover">{{ \App\Support\Format::phone($phone) }}</a></p>
                        @elseif (! $whatsapp)
                            <p class="mt-3 text-ui text-ink-soft">Kontak kelompok tidak dipublikasikan. Hubungi <a href="{{ route('public.contact') }}" class="rounded-control font-semibold text-brand underline underline-offset-4 hover:text-brand-hover">kantor distrik</a>.</p>
                        @endif
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ $shareUrl }}" target="_blank" rel="noopener" class="{{ $buttonSecondary }}">
                            <x-heroicon-o-share class="size-5" aria-hidden="true" />Bagikan ke WhatsApp
                        </a>
                        <button type="button" x-data="copyText(@js($url))" x-on:click="copy()" class="{{ $buttonSecondary }}">
                            <x-heroicon-o-link class="size-5" aria-hidden="true" x-show="! copied" />
                            <x-heroicon-o-check class="size-5 text-brand" aria-hidden="true" x-show="copied" x-cloak />
                            <span x-text="copied ? 'Tautan disalin' : 'Salin tautan'" aria-live="polite">Salin tautan</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @foreach (['lain' => ['Produk lain dari kelompok ini', $siblings], 'serupa' => ['Produk serupa', $similar]] as $key => [$heading, $items])
            @if ($items->isNotEmpty())
                <section class="mt-section" aria-labelledby="produk-{{ $key }}">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <h2 id="produk-{{ $key }}" class="font-serif text-display-sm font-semibold tracking-[-0.01em] text-ink">{{ $heading }}</h2>
                        @if ($key === 'lain')
                            <a href="{{ $profileUrl }}#produk" class="inline-flex min-h-11 items-center gap-1.5 rounded-control text-ui font-semibold text-brand underline-offset-4 hover:underline">Lihat profil kelompok<x-heroicon-o-arrow-right class="size-4" aria-hidden="true" /></a>
                        @else
                            <a href="{{ route('public.products', $sector ? ['bidang' => $sector->id] : []) }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-control text-ui font-semibold text-brand underline-offset-4 hover:underline">Lihat semua<x-heroicon-o-arrow-right class="size-4" aria-hidden="true" /></a>
                        @endif
                    </div>
                    <ul x-reveal.stagger.quick="fade-up" class="mt-5 grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-3 md:gap-x-5 lg:grid-cols-4">
                        @foreach ($items as $item)
                            <li class="min-w-0">@include('publik.products._card', ['product' => $item])</li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach
    </div>

    {{-- HP: tombol utama menempel di bawah layar setelah tombol aslinya tergulir lewat. --}}
    <div x-cloak x-show="shown" x-transition.opacity.duration.150ms class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-float md:hidden">
        <x-public.pill-link :href="$cta['href']" full :target="$cta['external'] ? '_blank' : null" :rel="$cta['external'] ? 'noopener' : null">{{ $cta['label'] }}</x-public.pill-link>
    </div>
</div>
</x-layouts.public>
