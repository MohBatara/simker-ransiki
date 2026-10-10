{{-- Bagian E (permasalahan) dan F (kebutuhan dukungan). Variabel: $key, $kind, $categories, $noun. --}}
@use('App\Support\ReportForm')

@php
    $title = $key === 'e' ? 'E. Permasalahan' : 'F. Kebutuhan dukungan';
    $description = $key === 'e'
        ? 'Masalah yang dihadapi kelompok bulan ini. Kosongkan bila tidak ada.'
        : 'Dukungan yang dibutuhkan dari pemerintah atau OPD. Kosongkan bila tidak ada.';
@endphp

<section id="bagian-{{ $key }}" tabindex="-1" aria-label="{{ $title }}" x-cloak x-show="section === '{{ $key }}'" class="focus:outline-none">
    <x-card :title="$title" :description="$description">
        <div class="space-y-4">
            <p x-show="! data.{{ $kind }}.length" class="text-ink-soft">Belum ada {{ $noun }} yang dicatat.</p>

            <template x-for="(row, i) in data.{{ $kind }}" :key="i">
                <div role="group" class="space-y-4 rounded-control border border-line p-4" x-bind:aria-label="`{{ ucfirst($noun) }} ${i + 1}`">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-ui font-semibold text-ink" x-text="`{{ ucfirst($noun) }} ${i + 1}`"></p>
                        <x-button variant="ghost" x-on:click="removeIssue('{{ $kind }}', i)" class="-my-2 -mr-2">Hapus</x-button>
                    </div>
                    <x-field label="Kategori" live="`{{ $kind }}.${i}.category`" bind-for="`{{ $kind }}-${i}-category`" class="md:max-w-sm">
                        <x-select
                            x-bind:id="`{{ $kind }}-${i}-category`"
                            x-model="row.category"
                            placeholder="Pilih kategori"
                            :options="$categories"
                            invalid-if="error(`{{ $kind }}.${i}.category`)"
                        />
                    </x-field>
                    <x-field label="Uraian" live="`{{ $kind }}.${i}.description`" bind-for="`{{ $kind }}-${i}-description`" hint="Jelaskan singkat. Wajib bila kategorinya Lainnya.">
                        <x-textarea
                            x-bind:id="`{{ $kind }}-${i}-description`"
                            rows="3"
                            maxlength="1000"
                            x-model="row.description"
                            invalid-if="error(`{{ $kind }}.${i}.description`)"
                        />
                    </x-field>
                </div>
            </template>

            <div class="flex flex-wrap items-center gap-3">
                <x-button variant="secondary" icon="plus" x-on:click="addIssue('{{ $kind }}')" x-bind:disabled="data.{{ $kind }}.length >= {{ ReportForm::MAX_ISSUES }}">Tambah {{ $noun }}</x-button>
                <p x-cloak x-show="data.{{ $kind }}.length >= {{ ReportForm::MAX_ISSUES }}" class="text-caption text-ink-muted">Paling banyak {{ ReportForm::MAX_ISSUES }} {{ $noun }}.</p>
            </div>
        </div>
    </x-card>
</section>
