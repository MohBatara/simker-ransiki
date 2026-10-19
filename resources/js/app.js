import Alpine from 'alpinejs';
import { numberPreview } from './numbers';
import reportForm, { clearReportBackups } from './report-form';
import heroSlider from './hero-slider';
import scrollDots from './scroll-dots';
import sectionNav from './section-nav';
import directory from './directory';
import adminToolbar from './admin-toolbar';
import { dropzone, formGuard } from './form-guard';
import { productGallery, scrollRow, stickyCta } from './products';
import readingProgress from './reading-progress';
import registerMotion from './motion';

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
            const item = { key: `${Date.now()}-${Math.random()}`, file, name: file.name, size: `${(file.size / 1048576).toFixed(1).replace('.', ',')} MB`, preview: /^image\/(jpeg|png)$/.test(file.type) ? URL.createObjectURL(file) : null, progress: 0, status: 'waiting', error: null, retryable: false };
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

Alpine.data('reportForm', reportForm);
Alpine.data('heroSlider', heroSlider);
Alpine.data('scrollDots', scrollDots);
Alpine.data('sectionNav', sectionNav);
Alpine.data('directory', directory);
Alpine.data('adminToolbar', adminToolbar);
Alpine.data('formGuard', formGuard);
Alpine.data('dropzone', dropzone);

/**
 * Toast pesan sistem (x-admin.toasts): hilang otomatis setelah 4 detik, berhenti saat disorot/difokuskan.
 */
Alpine.data('toasts', (initial = []) => ({
    items: [],
    next: 0,

    init() {
        initial.forEach((toast) => this.push(toast));
    },

    push({ tone = 'ok', message }) {
        if (!message) {
            return;
        }
        const toast = { id: this.next++, tone, message, visible: true, remaining: 4000, started: 0, timer: null };
        this.items.push(toast);
        this.resume(toast);
    },

    pause(toast) {
        clearTimeout(toast.timer);
        toast.remaining -= Date.now() - toast.started;
    },

    resume(toast) {
        toast.started = Date.now();
        toast.timer = setTimeout(() => this.dismiss(toast), Math.max(toast.remaining, 1000));
    },

    dismiss(toast) {
        clearTimeout(toast.timer);
        toast.visible = false;
        setTimeout(() => {
            this.items = this.items.filter((item) => item.id !== toast.id);
        }, 200);
    },
}));
Alpine.data('scrollRow', scrollRow);
Alpine.data('productGallery', productGallery);
Alpine.data('stickyCta', stickyCta);
Alpine.data('readingProgress', readingProgress);
registerMotion(Alpine);

/**
 * Tab Tulis/Pratinjau untuk isian Markdown (Berita & Kegiatan). HTML pratinjau dirender server dengan aturan
 * yang sama seperti halaman publik (HTML mentah dibuang, tautan berbahaya ditolak).
 */
Alpine.data('markdownPreview', (url) => ({
    tab: 'tulis',
    html: '',
    loading: false,
    failed: false,

    async load() {
        this.loading = true;
        this.failed = false;

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ body: this.$refs.source.value }),
            });
            if (!response.ok) throw new Error(response.status);
            this.html = (await response.json()).html;
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },
}));

/**
 * Kartu grafik (komponen x-chart). Chart.js dimuat lewat import() dinamis, sehingga hanya diunduh di halaman
 * yang punya grafik. Instans grafik disimpan di luar data Alpine agar tidak dibungkus proxy reaktif.
 */
Alpine.data('chart', (configs) => {
    let charts = [];

    return {
        showTable: false,

        // Satu config per canvas[data-panel] (beberapa grafik kecil bisa berada dalam satu kartu).
        async init() {
            const { renderChart } = await import('./charts');
            charts = [...this.$root.querySelectorAll('canvas[data-panel]')].map((canvas) => renderChart(canvas, configs[canvas.dataset.panel]));
        },

        destroy() {
            charts.forEach((chart) => chart.destroy());
        },
    };
});

/**
 * Baris tabel yang bisa diklik (<x-table.row href>): tautannya ada di sel "primary". Klik di bagian lain baris
 * diteruskan ke tautan itu. Tidak memakai lapisan yang menutupi <tr>, karena Safari tidak menjadikan <tr>
 * acuan posisi sehingga lapisannya bisa lepas dan menutupi baris lain. Ctrl/Cmd/klik tengah = tab baru.
 */
function openRowLink(event) {
    const row = event.target.closest('tr[data-row-href]');
    if (!row || event.defaultPrevented) {
        return;
    }
    if (event.target.closest('a, button, input, select, textarea, label, summary, [role="button"]')) {
        return;
    }
    // Pengguna sedang memilih teks di baris, bukan mengeklik.
    if (String(window.getSelection?.() ?? '') !== '') {
        return;
    }

    const link = row.querySelector('a[data-row-link]');
    if (!link) {
        return;
    }
    if (event.metaKey || event.ctrlKey || event.button === 1) {
        window.open(link.href, '_blank', 'noopener');
        return;
    }
    link.click();
}

document.addEventListener('click', openRowLink);
document.addEventListener('auxclick', (event) => event.button === 1 && openRowLink(event));

/**
 * Laci ringkasan kelompok dari kanan di atas daftar (layar ≥ 1280px). Klik baris memuat potongan HTML panel
 * (route .../ringkas, dicek Policy) tanpa pindah halaman; layar lebih kecil, klik dengan Ctrl/Cmd, atau
 * permintaan yang gagal tetap membuka halaman detail. ?pilih=id membuka laci sejak awal.
 */
Alpine.data('organizationPanel', (initiallyOpen = false) => ({
    loading: false,
    open: initiallyOpen && window.matchMedia('(min-width: 80rem)').matches,

    async select(event) {
        const link = event.target.closest('a');
        const row = event.target.closest('tr[data-panel-url]');

        if (!link || !link.matches('[data-row-link]') || !row || !window.matchMedia('(min-width: 80rem)').matches) {
            return;
        }
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
            return;
        }

        event.preventDefault();
        this.loading = true;
        this.open = true;

        try {
            const response = await fetch(row.dataset.panelUrl, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                throw new Error(response.status);
            }
            this.$refs.panel.innerHTML = await response.text();
            this.markSelected(row);

            const url = new URL(window.location.href);
            url.searchParams.set('pilih', row.dataset.id);
            window.history.replaceState(null, '', url);
            this.$nextTick(() => document.getElementById('panel-judul')?.focus());
        } catch {
            window.location.assign(link.href);
        } finally {
            this.loading = false;
        }
    },

    markSelected(selected) {
        for (const row of this.$root.querySelectorAll('tr[data-panel-url]')) {
            row.toggleAttribute('data-selected', row === selected);
        }
    },

    close() {
        if (!this.open) {
            return;
        }
        this.open = false;
        this.markSelected(null);

        const url = new URL(window.location.href);
        url.searchParams.delete('pilih');
        window.history.replaceState(null, '', url);
    },
}));

/**
 * Pilihan satuan (komponen x-unit-input). "value" adalah satuan akhir yang dihubungkan ke x-model luar
 * lewat x-modelable; satuan yang tidak ada di daftar ditampilkan sebagai "lainnya" + teks aslinya.
 */
Alpine.data('unitInput', (initial, units) => ({
    choice: '',
    other: '',
    value: initial ?? '',

    init() {
        this.sync(this.value);
        this.$watch('value', (value) => {
            if (value !== this.compose()) {
                this.sync(value);
            }
        });
        this.$watch('choice', () => (this.value = this.compose()));
        this.$watch('other', () => (this.value = this.compose()));
    },

    sync(value) {
        const unit = String(value ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
        this.choice = unit === '' ? '' : units.includes(unit) ? unit : 'lainnya';
        this.other = this.choice === 'lainnya' ? String(value).trim() : '';
    },

    compose() {
        return this.choice === 'lainnya' ? this.other.trim() : this.choice;
    },
}));

// Pratinjau "Terbaca: ..." di bawah input angka (komponen x-number-input).
Alpine.magic('number', () => numberPreview);

// Cadangan isian laporan di perangkat dihapus saat pengguna keluar.
document.addEventListener('submit', (event) => {
    if (event.target.matches('[data-logout]')) {
        clearReportBackups();
    }
});

/**
 * Cegah kirim ganda di koneksi lambat: setelah form (selain GET) dikirim, tombol kirimnya dinonaktifkan,
 * diberi aria-busy, dan labelnya diganti (data-processing pada x-button, bawaan "Memproses…").
 * Form dengan data-no-processing dilewati. Dipulihkan bila halaman dibuka lagi lewat tombol Back.
 */
function submitButtons(form) {
    const outside = form.id ? [...document.querySelectorAll(`button[type="submit"][form="${CSS.escape(form.id)}"]`)] : [];

    return [...form.querySelectorAll('button[type="submit"], button:not([type])'), ...outside];
}

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (event.defaultPrevented || form.matches('[data-no-processing]') || (form.getAttribute('method') ?? 'get').toLowerCase() === 'get') {
        return;
    }

    if (form.dataset.submitting) {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');

    // Ditunda satu giliran agar nilai tombol pengirim (bila ada) tetap ikut terkirim.
    setTimeout(() => {
        for (const button of submitButtons(form)) {
            const label = button.querySelector('[data-button-label]');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            if (label) {
                label.dataset.original = label.textContent;
                label.textContent = button.dataset.processing ?? 'Memproses…';
            }
        }
    });
});

// Form yang gagal divalidasi: fokus ke isian pertama yang salah (ringkasan x-form-errors ada di atasnya).
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-form-errors]')) {
        document.querySelector('[aria-invalid="true"]')?.focus();
    }
});

window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }

    for (const form of document.querySelectorAll('form[data-submitting]')) {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        for (const button of submitButtons(form)) {
            const label = button.querySelector('[data-button-label]');
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (label?.dataset.original !== undefined) {
                label.textContent = label.dataset.original;
            }
        }
    }
});

window.Alpine = Alpine;
Alpine.start();
