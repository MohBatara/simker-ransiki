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

window.Alpine = Alpine;
Alpine.start();
