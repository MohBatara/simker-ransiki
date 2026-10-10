@use('App\Support\Format')

<section id="bagian-h" tabindex="-1" aria-label="H. Periksa & ajukan" x-cloak x-show="section === 'h'" class="space-y-5 focus:outline-none">
    <x-card title="H. Periksa & ajukan" description="Periksa kelengkapan sebelum laporan diajukan ke OPD.">
        <ul class="divide-y divide-line">
            <template x-for="item in sections.filter((candidate) => candidate.key !== 'h')" :key="item.key">
                <li class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                    <div class="min-w-0">
                        <p class="font-semibold text-ink" x-text="item.title"></p>
                        <p x-show="! needsAttention(item.key)" class="text-caption text-ink-soft" x-text="summary(item.key)"></p>
                        <ul x-show="needsAttention(item.key)" class="mt-0.5 space-y-0.5 text-caption text-fix">
                            <template x-for="message in problems(item.key)">
                                <li x-text="message"></li>
                            </template>
                            <li x-show="! problems(item.key).length">Ada isian yang perlu diperbaiki.</li>
                        </ul>
                    </div>
                    <x-button variant="ghost" x-on:click="go(item.key)" class="-my-2">Ubah</x-button>
                </li>
            </template>
        </ul>
    </x-card>

    @if ($submitNote)
        <x-alert>{{ $submitNote }} Sementara itu, isian tetap bisa disimpan sebagai draf.</x-alert>
    @else
        <x-alert x-show="readyToSubmit">
            Tenggat {{ Format::date($report->deadline()) }}, pukul 23.59 WIT. Setelah diajukan, laporan dikunci sampai ditinjau OPD.
        </x-alert>
        <x-alert tone="wait" x-cloak x-show="! readyToSubmit">Lengkapi bagian yang ditandai sebelum mengajukan laporan.</x-alert>
    @endif
</section>
