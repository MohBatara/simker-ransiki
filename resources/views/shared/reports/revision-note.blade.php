{{-- Catatan permintaan revisi terakhir. Variabel: $report (dengan latestRevisionRequest.user.opd). --}}
@use('App\Support\Format')
@use('App\Enums\ReportStatus')

@if ($report->status === ReportStatus::Revision && ($revision = $report->latestRevisionRequest))
    <x-alert tone="fix" title="Perlu revisi">
        <p class="whitespace-pre-line break-words">{{ $revision->note }}</p>
        <p class="mt-1 text-caption text-ink-soft">
            Catatan dari {{ $revision->user?->name ?? 'peninjau' }}@if ($revision->user?->opd) ({{ $revision->user->opd->short_name ?: $revision->user->opd->name }})@endif, {{ \App\Support\Format::orEmpty(Format::date($revision->created_at)) }}.
            @if ($fixHint ?? false)
                Perbaiki lalu ajukan kembali.
            @endif
        </p>
    </x-alert>
@endif
