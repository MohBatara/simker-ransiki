{{--
    "Perlu dikerjakan": daftar tugas berprioritas (urutan dari controller/view) dengan tombol aksi langsung. Hanya tugas
    yang jumlahnya > 0. Tugas teratas memakai tombol utama, sisanya tombol bergaris.
    Variabel: $tasks = list of ['icon', 'title', 'hint' => ?string, 'action', 'href'].
--}}
<x-admin.card title="Perlu dikerjakan" flush>
    @if ($tasks === [])
        <div class="flex items-center gap-3 px-4 py-6 md:px-5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-ok-bg text-ok" aria-hidden="true">
                <x-heroicon-o-check class="size-5" />
            </span>
            <p class="text-ui text-ink-soft">Tidak ada yang perlu dikerjakan sekarang. Semua laporan yang diajukan sudah ditinjau.</p>
        </div>
    @else
        <ol class="divide-y divide-line">
            @foreach ($tasks as $task)
                <li class="flex flex-col gap-3 px-4 py-4 md:flex-row md:items-center md:gap-4 md:px-5">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-tint text-brand" aria-hidden="true">
                            <x-dynamic-component :component="'heroicon-o-'.$task['icon']" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold text-ink">{{ $task['title'] }}</p>
                            @if ($task['hint'] ?? null)
                                <p class="mt-0.5 text-caption text-ink-soft">{{ $task['hint'] }}</p>
                            @endif
                        </div>
                    </div>
                    <x-button :href="$task['href']" :variant="$loop->first ? 'primary' : 'secondary'" class="max-md:w-full">{{ $task['action'] }}</x-button>
                </li>
            @endforeach
        </ol>
    @endif
</x-admin.card>
