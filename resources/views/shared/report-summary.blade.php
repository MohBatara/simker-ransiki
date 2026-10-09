@use('App\Support\Format')

<x-card title="Ringkasan">
    <dl class="divide-y divide-line">
        <div class="flex justify-between gap-4 py-2 first:pt-0">
            <dt class="text-ink-soft">Status</dt>
            <dd><x-badge :tone="$report->status->badgeColor()">{{ $report->status->label() }}</x-badge></dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
            <dt class="text-ink-soft">Diajukan</dt>
            <dd>{{ Format::date($report->submitted_at) }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-2">
            <dt class="text-ink-soft">Pendapatan penjualan</dt>
            <dd class="tabular-nums">{{ Format::rupiah($report->income_sales) }}</dd>
        </div>
        <div class="flex justify-between gap-4 py-2 last:pb-0">
            <dt class="text-ink-soft">Laba (dihitung otomatis)</dt>
            <dd class="tabular-nums">{{ Format::rupiah($report->profit) }}</dd>
        </div>
    </dl>
</x-card>
