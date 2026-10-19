{{-- Riwayat tindak lanjut satu masalah/kebutuhan, terbaru dulu. Variabel: $followUps (dengan user.opd, opd). --}}
@use('App\Support\Format')

<ol class="space-y-3">
    @foreach ($followUps as $followUp)
        <li>
            <p class="text-ink">
                <span class="font-semibold">{{ $followUp->to_status->label() }}</span>
                @if ($followUp->from_status !== $followUp->to_status)
                    <span class="text-ink-soft">(sebelumnya {{ mb_strtolower($followUp->from_status->label()) }})</span>
                @endif
            </p>
            <p class="text-caption text-ink-muted">
                {{ \App\Support\Format::orEmpty(Format::date($followUp->acted_on)) }} · {{ $followUp->user?->nameWithAgency() ?? 'akun yang sudah dihapus' }}
                @if ($followUp->opd)
                    · penanggung jawab {{ $followUp->opd->short_name ?: $followUp->opd->name }}
                @endif
            </p>
            <p class="mt-1 whitespace-pre-line rounded-control bg-subtle px-3 py-2 text-ink-soft break-words">{{ $followUp->note }}</p>
        </li>
    @endforeach
</ol>
