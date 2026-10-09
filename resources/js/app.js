import Alpine from 'alpinejs';

Alpine.data('fileUpload', ({ maxMb, maxFiles, multiple }) => ({
    files: [],
    dragging: false,
    maxMb,
    maxFiles,

    get tooMany() {
        return this.files.length > this.maxFiles;
    },

    sync() {
        this.files = Array.from(this.$refs.input.files).map((file) => ({
            name: file.name,
            size: file.size,
            tooBig: file.size > this.maxMb * 1024 * 1024,
        }));
    },

    drop(event) {
        this.dragging = false;
        const dropped = Array.from(event.dataTransfer.files);
        const transfer = new DataTransfer();
        (multiple ? dropped : dropped.slice(0, 1)).forEach((file) => transfer.items.add(file));
        this.$refs.input.files = transfer.files;
        this.sync();
    },

    formatSize(bytes) {
        if (bytes < 1024 * 1024) {
            return Math.max(1, Math.round(bytes / 1024)).toLocaleString('id-ID') + ' KB';
        }

        return (bytes / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' MB';
    },
}));

/**
 * Satu modal untuk tambah dan ubah data pada daftar sederhana (mis. data master).
 * initial: isian lama setelah validasi gagal, agar modal terbuka kembali dengan isian yang sama.
 */
Alpine.data('recordForm', ({ storeUrl, updateUrl, destroyUrl, initial, hadErrors }) => ({
    mode: initial?.id ? 'edit' : 'create',
    record: initial ?? {},
    deleting: {},
    hadErrors,

    get action() {
        return this.mode === 'edit' ? updateUrl.replace('__ID__', this.record.id) : storeUrl;
    },

    get deleteAction() {
        return destroyUrl.replace('__ID__', this.deleting.id);
    },

    create() {
        this.mode = 'create';
        this.record = {};
        this.$dispatch('open-modal', 'record-form');
    },

    edit(record) {
        this.mode = 'edit';
        this.record = { ...record };
        this.$dispatch('open-modal', 'record-form');
    },

    remove(record) {
        this.deleting = record;
        this.$dispatch('open-modal', 'record-delete');
    },

    // Pesan error lama dirender server; muat ulang agar tidak terbawa ke isian berikutnya.
    closed() {
        if (this.hadErrors) {
            window.location.reload();
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
