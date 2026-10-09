@use('App\Support\Format')

<x-layouts.app
    :title="'Laporan '.Format::month($report->period)"
    :description="$report->organization->name"
    :back="route('kelompok.dashboard')"
>
    <div class="max-w-2xl">
        @include('shared.report-summary')
    </div>
</x-layouts.app>
