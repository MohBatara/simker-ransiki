import Alpine from 'alpinejs';

const HEIC_MESSAGE = "Format HEIC belum didukung. Ubah pengaturan kamera iPhone ke 'Paling Kompatibel', atau kirim ulang sebagai JPG.";

/**
 * Perkecil foto di browser sebelum dikirim agar hemat kuota di koneksi lambat.
 * Server tetap memproses ulang; hasil di sini hanya penghematan, bukan jaminan keamanan.
 */
async function shrinkImage(file, maxSide = 1600) {
    if (!['image/jpeg', 'image/png'].includes(file.type) || !window.createImageBitmap) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close?.();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, file.type, 0.8));

        return blob && blob.size < file.size ? new File([blob], file.name, { type: file.type }) : file;
    } catch {
        return file;
    }
}

function uploadErrorMessage(xhr) {
    if (xhr.status === 422) {
        try {
            const data = JSON.parse(xhr.responseText);
            return data.errors?.file?.[0] ?? data.message;
        } catch {
            // jatuh ke pesan umum di bawah
        }
    }

    return {
        0: 'Gagal mengunggah. Periksa koneksi lalu coba lagi.',
        403: 'Akun Anda tidak punya akses untuk mengunggah di sini.',
        404: 'Akun Anda tidak punya akses untuk mengunggah di sini.',
        413: 'File terlalu besar untuk diterima server. Perkecil file lalu coba lagi.',
        419: 'Sesi berakhir. Muat ulang halaman lalu coba lagi.',
        429: 'Terlalu banyak unggahan. Tunggu sebentar lalu coba lagi.',
    }[xhr.status] ?? 'Gagal mengunggah. Coba lagi beberapa saat lagi.';
}

/**
 * Unggah satu file per permintaan lewat XHR (fetch belum punya progres unggah). Alamat tujuan dibaca
 * dari data-url elemen saat mengirim, sehingga bisa diganti lewat x-bind (mis. modal foto produk).
 */
Alpine.data('uploader', ({ kind, maxMb, remaining, multiple }) => ({
    queue: [],
    remaining,
    dragging: false,
    busy: false,

    get needsReload() {
        return !this.busy && this.queue.some((item) => item.status === 'done') && this.queue.some((item) => item.status === 'error');
    },

    add(fileList) {
        const files = Array.from(fileList).slice(0, multiple ? undefined : 1);

        for (const file of files) {
            const item = { key: `${Date.now()}-${Math.random()}`, file, name: file.name, progress: 0, status: 'waiting', error: null, retryable: false };
            const problem = this.problem(file);

            if (problem) {
                Object.assign(item, { status: 'error', error: problem });
            } else if (this.remaining < 1) {
                Object.assign(item, { status: 'error', error: 'Batas jumlah file sudah tercapai. Hapus file lama untuk menambah yang baru.' });
            } else {
                this.remaining--;
            }

            this.queue.push(item);
        }

        this.run();
    },

    drop(event) {
        this.dragging = false;
        this.add(event.dataTransfer.files);
    },

    problem(file) {
        if (/\.hei[cf]$/i.test(file.name) || /hei[cf]/i.test(file.type)) {
            return HEIC_MESSAGE;
        }

        const allowed = kind === 'photo' ? ['image/jpeg', 'image/png'] : ['image/jpeg', 'image/png', 'application/pdf'];
        if (!allowed.includes(file.type)) {
            return kind === 'photo' ? 'Tipe file tidak didukung. Gunakan foto JPG atau PNG.' : 'Tipe file tidak didukung. Gunakan JPG, PNG, atau PDF.';
        }

        return null;
    },

    async run() {
        if (this.busy) {
            return;
        }
        this.busy = true;

        for (const item of this.queue) {
            if (item.status === 'waiting') {
                await this.upload(item);
            }
        }

        this.busy = false;

        // Semua berhasil: muat ulang agar daftar file di halaman ikut diperbarui.
        if (this.queue.length && this.queue.every((item) => item.status === 'done')) {
            setTimeout(() => window.location.reload(), 500);
        }
    },

    async upload(item) {
        item.status = 'preparing';
        const file = await shrinkImage(item.file);

        if (file.size > maxMb * 1024 * 1024) {
            Object.assign(item, { status: 'error', error: `Ukuran file paling besar ${maxMb} MB. Perkecil file lalu coba lagi.` });
            return;
        }

        item.status = 'uploading';

        await new Promise((resolve) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', this.$root.dataset.url);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').content);
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable) {
                    item.progress = Math.round((event.loaded / event.total) * 100);
                }
            };
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    Object.assign(item, { status: 'done', progress: 100 });
                } else {
                    Object.assign(item, { status: 'error', error: uploadErrorMessage(xhr), retryable: ![403, 404, 422].includes(xhr.status) });
                }
                resolve();
            };
            xhr.onerror = () => {
                Object.assign(item, { status: 'error', error: uploadErrorMessage(xhr), retryable: true });
                resolve();
            };

            const data = new FormData();
            data.append('file', file, item.name);
            xhr.send(data);
        });
    },

    retry(item) {
        Object.assign(item, { status: 'waiting', error: null, progress: 0, retryable: false });
        this.run();
    },

    statusText(item) {
        return {
            waiting: 'Menunggu',
            preparing: 'Menyiapkan',
            uploading: `Mengunggah ${item.progress}%`,
            done: 'Selesai',
            error: 'Gagal',
        }[item.status];
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

/**
 * Tombol salin teks. navigator.clipboard butuh HTTPS; di HTTP dipakai cara lama sebagai cadangan.
 */
Alpine.data('copyText', (text) => ({
    copied: false,

    async copy() {
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const field = document.createElement('textarea');
            field.value = text;
            field.setAttribute('readonly', '');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();
            document.execCommand('copy');
            field.remove();
        }

        this.copied = true;
        setTimeout(() => (this.copied = false), 2500);
    },
}));

window.Alpine = Alpine;
Alpine.start();
