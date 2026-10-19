{{--
    Pembuka setiap form tambah/ubah: satu kalimat kecil "Kolom bertanda * wajib diisi." lalu ringkasan error
    ("Ada N isian yang perlu diperbaiki", x-form-errors) bila validasi gagal. Isian opsional tidak diberi label khusus.
--}}
<div {{ $attributes->class('space-y-3') }}>
    <p class="text-label text-ink-3">Kolom bertanda <span class="text-fix" aria-hidden="true">*</span><span class="sr-only">bintang</span> wajib diisi.</p>
    <x-form-errors />
</div>
