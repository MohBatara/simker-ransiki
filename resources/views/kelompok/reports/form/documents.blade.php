<section id="bagian-g" tabindex="-1" aria-label="G. Dokumentasi" x-cloak x-show="section === 'g'" class="focus:outline-none">
    @include('shared.attachments', [
        'title' => 'G. Dokumentasi',
        'description' => 'Foto kegiatan, nota, atau dokumen pendukung. JPG, PNG, atau PDF, maksimal 10 file. Opsional.',
        'uploadUrl' => route('attachments.store', ['laporan', $report->id]),
        'camera' => true,
    ])
</section>
