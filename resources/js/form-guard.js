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

/** Kotak unggah satu file untuk form biasa (x-admin.dropzone): tarik-dan-lepas, pratinjau, nama, dan ukuran file. */
export function dropzone(current = null) {
    return {
        over: false,
        name: null,
        size: null,
        preview: current,

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

        changed(event) {
            const file = event.target.files[0];
            if (!file) {
                this.name = null;
                this.size = null;
                this.preview = current;
                return;
            }
            this.name = file.name;
            this.size = `${(file.size / 1048576).toFixed(1).replace('.', ',')} MB`;
            this.preview = file.type.startsWith('image/') ? URL.createObjectURL(file) : current;
        },

        clear() {
            this.$refs.input.value = '';
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        },
    };
}
