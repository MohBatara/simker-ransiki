{{--
    Daftar lampiran + unggah. Variabel: $attachments (dengan relasi uploader dan attachable),
    $uploadUrl (null bila pengguna tidak boleh mengunggah), $title, $description (opsional),
    $camera (opsional: tombol "Ambil foto" untuk kamera HP).
--}}
@use('App\Support\Format')

@php
    $remaining = max(0, App\Actions\Files\StoreAttachment::MAX_FILES - $attachments->count());
@endphp

<x-card :title="$title" :description="$description ?? 'JPG, PNG, atau PDF. Maksimal 10 file.'">
    <div class="space-y-4" x-data="{ deleting: {} }">
        @if ($attachments->isEmpty())
            <p class="text-ink-soft">Belum ada file.</p>
        @else
            <ul class="divide-y divide-line rounded-control border border-line">
                @foreach ($attachments as $attachment)
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5">
                        <x-dynamic-component :component="str_starts_with($attachment->mime_type, 'image/') ? 'heroicon-o-photo' : 'heroicon-o-document-text'" class="size-5 shrink-0 text-ink-muted" />
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('attachments.show', $attachment) }}" target="_blank" rel="noopener" class="block truncate rounded-control text-brand hover:text-brand-hover">{{ $attachment->original_name }}</a>
                            <p class="text-caption text-ink-muted">
                                {{ Format::fileSize($attachment->size) }} · {{ Format::date($attachment->created_at) }}@if ($attachment->uploader) · {{ $attachment->uploader->name }}@endif
                            </p>
                        </div>
                        <div class="flex gap-2 max-sm:w-full max-sm:justify-end">
                            <x-button variant="ghost" icon="arrow-down-tray" :href="route('attachments.download', $attachment)">Unduh</x-button>
                            @can('delete', $attachment)
                                <x-button variant="ghost" x-on:click="deleting = {{ Js::from(['url' => route('attachments.destroy', $attachment), 'name' => $attachment->original_name]) }}; $dispatch('open-modal', 'attachment-delete')">Hapus</x-button>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($uploadUrl)
            @if ($remaining > 0)
                <x-file-upload :url="$uploadUrl" :remaining="$remaining" :camera="$camera ?? false" :hint="'JPG, PNG, atau PDF. Maksimal 10 MB per file, sisa '.$remaining.' file.'" />
            @else
                <p class="text-caption text-ink-muted">Sudah ada 10 file. Hapus file lama untuk menambah yang baru.</p>
            @endif
        @endif

        <x-modal name="attachment-delete" title="Hapus file">
            <p>Hapus <strong class="text-ink" x-text="deleting.name"></strong>? File yang dihapus tidak bisa dikembalikan.</p>
            <x-slot:footer>
                <x-button variant="ghost" x-on:click="$dispatch('close-modal', 'attachment-delete')">Batal</x-button>
                <form method="POST" x-bind:action="deleting.url">
                    @csrf
                    @method('DELETE')
                    <x-button variant="danger" type="submit" processing="Menghapus…">Hapus</x-button>
                </form>
            </x-slot:footer>
        </x-modal>
    </div>
</x-card>
