@use('App\Support\Format')

<x-layouts.public
    :title="$organization->name"
    :description="$organization->description ? Str::limit($organization->description, 160) : $organization->institutionType->name.' di '.$organization->village->name.', Distrik Ransiki.'"
    :image="$ogImage"
>
    <x-public.band :title="$organization->name">
        <x-slot:top>
            <nav aria-label="Remah roti" class="mb-4 text-caption text-forest-soft">
                <a href="{{ route('public.directory') }}" class="rounded-control hover:text-paper">Direktori</a>
                <span aria-hidden="true"> / </span>
                <span>{{ $organization->name }}</span>
            </nav>
            <div class="mb-4 flex flex-wrap gap-2">
                <x-public.pill tone="dark">{{ $organization->institutionType->name }}</x-public.pill>
                <x-public.pill tone="dark">{{ $organization->village->name }}</x-public.pill>
                @if ($organization->status === App\Enums\OrganizationStatus::Inactive)
                    <x-public.pill tone="dark">{{ $organization->status->label() }}</x-public.pill>
                @endif
                @if ($organization->profile_status === App\Enums\ProfileStatus::Verified)
                    <x-public.pill tone="dark">Profil diverifikasi OPD</x-public.pill>
                @endif
                @if ($organization->founded_year)
                    <x-public.pill tone="dark">Berdiri {{ $organization->founded_year }}</x-public.pill>
                @endif
            </div>
        </x-slot:top>
        <p class="mt-3 text-body text-forest-soft">{{ $organization->sectors->pluck('name')->join(' · ') }}</p>
        @if ($updatedAt)
            <p class="mt-2 text-caption text-forest-soft">Data diperbarui {{ Format::date($updatedAt) }}</p>
        @endif
    </x-public.band>

    <div class="mx-auto grid max-w-public gap-10 px-4 py-10 md:px-6 lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-14">
        <div class="contents lg:block">
            <div class="order-3 space-y-12">
                @if ($organization->description)
                    <p class="max-w-[42rem] whitespace-pre-line text-body text-ink">{{ $organization->description }}</p>
                @endif

                <section id="produk" class="scroll-mt-6">
                    @include('publik._section-heading', ['title' => 'Produk'])
                    @if ($organization->products->isEmpty())
                        <p class="mt-4 text-body text-ink-soft">Belum ada produk yang dipublikasikan.</p>
                    @else
                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            @foreach ($organization->products as $product)
                                <article class="overflow-hidden rounded-card border border-line bg-paper">
                                    @if ($sources = $product->photoSources())
                                        {{-- Foto utuh (object-contain), tidak dipotong. --}}
                                        <x-public.picture :image="$sources" :alt="$product->name" sizes="(min-width: 64rem) 20rem, (min-width: 40rem) 50vw, 100vw" width="400" height="300" frame="block bg-subtle" class="aspect-[4/3] w-full object-contain" />
                                    @else
                                        <div class="flex aspect-[4/3] items-center justify-center bg-subtle text-display font-semibold text-ink-muted" aria-hidden="true">{{ $product->initials() }}</div>
                                    @endif
                                    <div class="p-4">
                                        <h3 class="font-semibold text-ink">{{ $product->name }}</h3>
                                        @if ($product->capacity || $product->unit)
                                            <p class="text-caption text-ink-muted">{{ $product->capacity ?? 'Satuan: '.$product->unit }}</p>
                                        @endif
                                        @if ($product->description)
                                            <p class="mt-1 text-ui text-ink-soft">{{ $product->description }}</p>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>

                @if ($organization->production_capacity || $organization->markets)
                    <section>
                        @include('publik._section-heading', ['title' => 'Informasi usaha'])
                        <dl class="mt-4 divide-y divide-line text-body">
                            @foreach (array_filter(['Kapasitas produksi' => $organization->production_capacity, 'Pasar' => $organization->markets]) as $label => $value)
                                <div class="grid gap-1 py-3 sm:grid-cols-[12rem_1fr] sm:gap-4">
                                    <dt class="text-ink-soft">{{ $label }}</dt>
                                    <dd class="whitespace-pre-line text-ink">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif
            </div>
        </div>

        <aside class="order-2 h-fit space-y-5 lg:row-span-2">
            <section class="rounded-card border border-line bg-paper p-4 md:p-5" aria-labelledby="kontak">
                <h2 id="kontak" class="text-ui font-semibold text-ink">Kontak</h2>
                @if ($contact)
                    <dl class="mt-3 space-y-2 text-ui">
                        @if ($contact->leader_name)
                            <div><dt class="text-caption text-ink-muted">Ketua</dt><dd>{{ $contact->leader_name }}</dd></div>
                        @endif
                        @if ($contact->phone)
                            <div>
                                <dt class="text-caption text-ink-muted">Telepon</dt>
                                <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}" class="rounded-control text-brand hover:text-brand-hover">{{ $contact->phone }}</a></dd>
                            </div>
                        @endif
                        @if ($contact->email)
                            <div><dt class="text-caption text-ink-muted">Email</dt><dd><a href="mailto:{{ $contact->email }}" class="break-all rounded-control text-brand hover:text-brand-hover">{{ $contact->email }}</a></dd></div>
                        @endif
                        @if ($contact->address)
                            <div><dt class="text-caption text-ink-muted">Alamat</dt><dd class="whitespace-pre-line">{{ $contact->address }}</dd></div>
                        @endif
                    </dl>
                    @if ($contact->phone)
                        <x-button variant="secondary" icon="chat-bubble-left-right" :href="Format::whatsappUrl($contact->phone)" class="mt-4 w-full" rel="noopener">Hubungi lewat WhatsApp</x-button>
                    @endif
                @else
                    <p class="mt-2 text-ui text-ink-soft">Kontak kelompok tidak dipublikasikan. Hubungi kantor distrik.</p>
                    <a href="{{ route('public.contact') }}" class="mt-3 inline-block rounded-control text-ui text-brand hover:text-brand-hover">Kontak kantor distrik</a>
                @endif
            </section>

            @if ($showLegal)
                <section class="rounded-card border border-line bg-paper p-4 md:p-5" aria-labelledby="legalitas">
                    <h2 id="legalitas" class="text-ui font-semibold text-ink">Legalitas</h2>
                    <ul class="mt-3 space-y-1.5 text-ui text-ink">
                        @if ($organization->legal_status === App\Enums\LegalStatus::Registered)
                            <li>Status hukum: terdaftar</li>
                        @endif
                        @foreach ($organization->licenses as $license)
                            <li>{{ $license->type }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.public>
