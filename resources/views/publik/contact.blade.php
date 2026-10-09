@use('App\Support\Format')

<x-layouts.public title="Kontak" description="Kontak Kantor Distrik Ransiki dan daftar OPD terkait.">
    <div class="max-w-[42rem]">
        <h1 class="text-display-sm font-semibold text-ink md:text-display">Kontak</h1>

        <section class="mt-8">
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
                        <div class="grid gap-1 py-3 sm:grid-cols-[10rem_1fr] sm:gap-4">
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

        <section class="mt-12">
            @include('publik._section-heading', ['title' => 'OPD terkait'])
            <ul class="mt-4 divide-y divide-line">
                @foreach ($opds as $opd)
                    <li class="py-3">
                        <p class="text-body font-semibold text-ink">{{ $opd->name }}</p>
                        @if ($opd->description)
                            <p class="mt-1 text-ui text-ink-soft">{{ $opd->description }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.public>
