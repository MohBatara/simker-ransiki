import { applyImageAccept, isImageFile, prepareImage, PREPARE_ERROR } from './image-upload';

/**
 * Bar aksi form (x-admin.form-bar): menandai "Perubahan belum disimpan" setelah isian berubah dan memperingatkan saat
 * meninggalkan halaman sebelum menyimpan. Form = <form> induk bar, atau elemen dengan id dari data-form.
 */
export function formGuard(initiallyDirty = false) {
    return {
        dirty: initiallyDirty,
        submitting: false,

        init() {
            const form = this.$el.closest('form') ?? document.getElementById(this.$el.dataset.form ?? '');
            if (!form) {
                return;
            }
            const mark = (event) => {
                if (!event.target.closest?.('[data-no-dirty]')) {
                    this.dirty = true;
                }
            };
            form.addEventListener('input', mark);
            form.addEventListener('change', mark);
            form.addEventListener('submit', () => {
                this.submitting = true;
            });
            window.addEventListener('beforeunload', (event) => {
                if (this.dirty && !this.submitting) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },
    };
}

const megabytes = (bytes) => `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB`;

/**
 * Kotak unggah satu file untuk form biasa (x-admin.dropzone): tarik-dan-lepas, pratinjau, nama, dan ukuran file. Foto
 * (input ber-data-image-accept) disiapkan dulu (HEIC → JPG, diperkecil, tanpa metadata) lalu menggantikan file di input;
 * selama itu form tidak bisa dikirim. Setelah diganti, event change dikirim ulang agar pendengar halaman (mis. pratinjau
 * slide) membaca file hasil persiapan.
 */
export function dropzone(current = null) {
    return {
        over: false,
        name: null,
        size: null,
        preview: current,
        preparing: false,
        error: null,
        replacing: false,

        init() {
            applyImageAccept(this.$refs.input);
            this.$refs.input.form?.addEventListener('submit', (event) => {
                if (this.preparing) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            }, true);
        },

        drop(event) {
            this.over = false;
            const file = event.dataTransfer.files[0];
            if (!file) {
                return;
            }
            const transfer = new DataTransfer();
            transfer.items.add(file);
            this.$refs.input.files = transfer.files;
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        async changed(event) {
            if (this.replacing) {
                return;
            }
            const input = event.target;
            const file = input.files[0];
            this.error = null;
            if (!file) {
                this.name = null;
                this.size = null;
                this.preview = current;
                return;
            }
            this.name = file.name;
            this.size = megabytes(file.size);

            if (!input.dataset.imageAccept || !isImageFile(file)) {
                this.preview = file.type.startsWith('image/') ? URL.createObjectURL(file) : current;
                return;
            }

            this.preparing = true;
            this.preview = current;
            try {
                const prepared = await prepareImage(file);
                const transfer = new DataTransfer();
                transfer.items.add(prepared);
                input.files = transfer.files;
                this.name = prepared.name;
                this.size = megabytes(prepared.size);
                this.preview = URL.createObjectURL(prepared);
                this.replacing = true;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } catch {
                input.value = '';
                this.name = null;
                this.size = null;
                this.error = PREPARE_ERROR;
            } finally {
                this.replacing = false;
                this.preparing = false;
            }
        },

        clear() {
            this.$refs.input.value = '';
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        },
    };
}
