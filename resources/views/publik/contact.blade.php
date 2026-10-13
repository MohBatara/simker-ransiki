@use('App\Support\Format')

<x-layouts.public title="Kontak" description="Kontak Kantor Distrik Ransiki dan daftar OPD terkait.">
    <x-public.band title="Kontak" :crumbs="['Kontak' => null]" lead="Kantor Distrik Ransiki dan OPD yang membina kelompok usaha." />

    <div class="mx-auto grid max-w-public gap-6 px-4 py-section md:px-6 lg:grid-cols-2">
        <section class="rounded-public border border-line bg-paper shadow-card p-4 md:p-5">
            @include('publik._section-heading', ['title' => 'Kantor Distrik Ransiki'])
            @php
                $rows = array_filter([
                    'Alamat' => $settings['office_address'],
                    'Telepon' => $settings['office_phone'],
                    'WhatsApp' => $settings['office_whatsapp'],
                    'Email' => $settings['office_email'],
                    'Jam layanan' => $settings['office_hours'],
                ]);
            @endphp
            @if ($rows)
                <dl class="mt-4 divide-y divide-line text-body">
                    @foreach ($rows as $label => $value)
                        <div class="grid gap-1 py-3 sm:grid-cols-[8rem_1fr] sm:gap-4">
                            <dt class="text-ink-soft">{{ $label }}</dt>
                            <dd class="text-ink">
                                @switch($label)
                                    @case('Telepon')
                                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $value) }}" class="rounded-control text-brand hover:text-brand-hover">{{ $value }}</a>
                                        @break
                                    @case('WhatsApp')
                                        <a href="{{ Format::whatsappUrl($value) }}" class="rounded-control text-brand hover:text-brand-hover" rel="noopener">{{ $value }}</a>
                                        @break
                                    @case('Email')
                                        <a href="mailto:{{ $value }}" class="rounded-control text-brand hover:text-brand-hover">{{ $value }}</a>
                                        @break
                                    @default
                                        <span class="whitespace-pre-line">{{ $value }}</span>
                                @endswitch
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="mt-4 text-body text-ink-soft">Kontak kantor sedang disiapkan.</p>
            @endif
        </section>

        <section class="rounded-public border border-line bg-paper shadow-card p-4 md:p-5">
            @include('publik._section-heading', ['title' => 'OPD terkait'])
            <ul class="mt-4 divide-y divide-line">
                @foreach ($opds as $opd)
                    <li class="py-3">
                        <h3 class="font-serif text-card-title font-semibold text-ink">{{ $opd->name }}</h3>
                        @if ($opd->description)
                            <p class="mt-1 text-ui text-ink-soft">{{ $opd->description }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.public>
