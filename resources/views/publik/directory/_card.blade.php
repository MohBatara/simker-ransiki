{{--
    Kartu kelompok Direktori. Variabel: $organization (DirectorySearch::results()), $search (sorotan kata kunci).
    Tampilan Kartu (bawaan) dan Daftar (varian list-view) dari markup yang sama; seluruh kartu menaut lewat tautan nama
    (lapisan after:inset-0). Foto: foto produk publik → foto bidang usaha → panel cream berinisial.
--}}
@use('App\Enums\OrganizationStatus')
@use('App\Enums\ProfileStatus')
@use('App\Support\Format')
@use('App\Support\PublicImages')

@php
    $url = route('public.organization', $organization->slug);
    $sectorWithPhoto = $organization->sectors->first(fn ($sector) => filled($sector->photo_path));
    $photo = $organization->products->first()?->photoSources() ?? ($sectorWithPhoto ? PublicImages::sectorPhoto($sectorWithPhoto) : null);
    $initials = collect(preg_split('/\s+/', $organization->name))->filter(fn ($word) => ! preg_match('/^\d+$/', $word))->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $verified = $organization->profile_status === ProfileStatus::Verified;
    $sectors = $organization->sectors;
@endphp

<article class="group relative flex h-full flex-col overflow-hidden rounded-public border border-line bg-paper shadow-card transition-[translate,box-shadow] duration-150 hover:-translate-y-0.5 hover:shadow-float has-[a:focus-visible]:outline-2 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-brand motion-reduce:transition-none motion-reduce:hover:translate-y-0 list-view:flex-row list-view:items-center list-view:gap-4 list-view:p-3">
    <div class="relative aspect-[16/10] shrink-0 overflow-hidden bg-cream list-view:aspect-square list-view:size-24 list-view:rounded-control">
        @if ($photo)
            <x-public.picture :image="$photo" alt="" sizes="(min-width: 80rem) 15rem, (min-width: 48rem) 50vw, 100vw" width="400" height="250" frame="block size-full" class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100" />
        @else
            <span class="flex size-full items-center justify-center font-serif text-hero-sm font-semibold text-clay list-view:text-page-title" aria-hidden="true">{{ $initials }}</span>
        @endif
        {{-- Jenis (kiri) dan lencana verifikasi (kanan) sebaris; jenis dipotong bila sempit. Kartu 3 kolom: lencana ikon saja. --}}
        <div class="absolute inset-x-3 top-3 flex items-start justify-between gap-2 list-view:hidden">
            <span class="inline-flex h-7 min-w-0 items-center rounded-full bg-paper px-3 text-caption font-semibold text-ink shadow-soft"><span class="truncate">{{ $organization->institutionType->name }}</span></span>
            @if ($verified)
                <span class="inline-flex h-7 shrink-0 items-center gap-1 rounded-full bg-brand px-2 text-caption font-semibold text-paper xl:size-7 xl:justify-center xl:px-0" title="Profil diverifikasi OPD">
                    <x-heroicon-o-check-badge class="size-4 shrink-0" aria-hidden="true" /><span class="pr-0.5 xl:sr-only">Diverifikasi OPD</span>
                </span>
            @endif
        </div>
    </div>

    <div class="flex min-w-0 flex-1 flex-col p-4 md:p-5 list-view:p-0">
        {{-- Tampilan Daftar: jenis dan lencana sebaris di atas nama (di Kartu keduanya menumpang di foto). --}}
        <div class="mb-1.5 hidden flex-wrap items-center gap-x-3 gap-y-1 text-caption font-semibold list-view:flex">
            <span class="text-clay">{{ $organization->institutionType->name }}</span>
            @if ($verified)
                <span class="inline-flex items-center gap-1 text-brand" title="Profil diverifikasi OPD"><x-heroicon-o-check-badge class="size-4" aria-hidden="true" />Diverifikasi OPD</span>
            @endif
        </div>
        <h2 class="font-serif text-title-sm font-semibold text-ink list-view:text-card-title">
            <a href="{{ $url }}" title="{{ $organization->name }}" class="line-clamp-2 rounded-control after:absolute after:inset-0 focus-visible:outline-none group-hover:text-brand">{!! $search->highlight($organization->name) !!}</a>
        </h2>
        @if ($organization->status === OrganizationStatus::Inactive)
            <x-public.pill class="mt-2 self-start">{{ $organization->status->label() }}</x-public.pill>
        @endif
        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
            <p class="flex items-center gap-1.5 text-ui text-ink-soft">
                <x-heroicon-o-map-pin class="size-4 shrink-0 text-brand" aria-hidden="true" />{{ $organization->village->name }}
            </p>
            @if ($sectors->isNotEmpty())
                <ul class="flex flex-wrap gap-1.5 list-view:max-md:hidden" aria-label="Bidang usaha">
                    @foreach ($sectors->take(2) as $sector)
                        <li><x-public.pill>{{ $sector->name }}</x-public.pill></li>
                    @endforeach
                    @if ($sectors->count() > 2)
                        <li><x-public.pill :title="$sectors->slice(2)->pluck('name')->join(', ')"><span class="sr-only">dan </span>+{{ $sectors->count() - 2 }}</x-public.pill></li>
                    @endif
                </ul>
            @endif
        </div>
        <div class="mt-auto pt-4 list-view:pt-1">
            <div class="flex items-center justify-between gap-3 border-t border-line pt-3 text-ui text-ink-soft list-view:border-0 list-view:pt-0">
                <span class="tabular-nums">{{ Format::number($organization->public_products_count) }} produk</span>
                <x-heroicon-o-arrow-right class="size-4 text-brand transition-transform duration-150 group-hover:translate-x-1 motion-reduce:transition-none list-view:hidden" aria-hidden="true" />
            </div>
        </div>
    </div>

    <x-heroicon-o-arrow-right class="mr-2 hidden size-5 shrink-0 text-brand transition-transform duration-150 group-hover:translate-x-1 motion-reduce:transition-none list-view:block" aria-hidden="true" />
</article>
