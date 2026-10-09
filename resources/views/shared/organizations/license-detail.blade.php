{{-- Detail izin beserta lampirannya, untuk Admin, OPD, dan Kelompok. Variabel: $organization, $license, $attachments, $backUrl. --}}
@use('App\Support\Format')

<x-layouts.app :title="$license->type" :description="$organization->name" :back="$backUrl">
    <div class="max-w-2xl space-y-5">
        <x-card title="Data izin">
            <dl class="divide-y divide-line">
                @foreach ([
                    'Status' => null,
                    'Nomor izin' => $license->number ?? '—',
                    'Tanggal diajukan' => Format::date($license->submitted_on),
                    'Tanggal terbit' => Format::date($license->issued_on),
                    'Catatan' => $license->notes ?? '—',
                ] as $label => $value)
                    <div class="grid gap-1 py-2.5 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,12rem)_1fr] sm:gap-4">
                        <dt class="text-ink-soft">{{ $label }}</dt>
                        <dd class="whitespace-pre-line">
                            @if ($label === 'Status')
                                <x-badge :tone="$license->status->badgeColor()">{{ $license->status->label() }}</x-badge>
                            @else
                                {{ $value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        @include('shared.attachments', [
            'title' => 'Lampiran izin',
            'description' => 'Mis. scan surat izin atau bukti pengajuan. JPG, PNG, atau PDF.',
            'uploadUrl' => auth()->user()->can('update', $license) ? route('attachments.store', ['izin', $license]) : null,
        ])
    </div>
</x-layouts.app>
