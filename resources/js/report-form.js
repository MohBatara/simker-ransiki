import { formatNumber, parseNumber } from './numbers';

const BACKUP_PREFIX = 'simker:laporan:';

const BLANK_ITEM = { choice: '', product_id: null, product_name: '', production_qty: '', production_unit: '', sales_qty: '', sales_unit: '', revenue: '' };
const BLANK_ISSUE = { id: null, category: '', description: '' };

// Kunci error dari server → bagian form.
const SECTION_OF = [
    [/^(no_activity|activity_|achievements)/, 'a'],
    [/^items/, 'b'],
    [/^(members_|workforce_)/, 'c'],
    [/^(income_|costs)/, 'd'],
    [/^problems/, 'e'],
    [/^supports/, 'f'],
];

const STATUS_TEXT = {
    saving: 'Menyimpan…',
    dirty: 'Ada perubahan yang belum tersimpan.',
    offline: 'Belum tersimpan. Koneksi terputus, isian aman di perangkat ini.',
    invalid: 'Belum tersimpan. Perbaiki isian yang ditandai.',
    error: 'Belum tersimpan. Akan dicoba lagi.',
    conflict: 'Laporan ini sudah diubah dari perangkat atau akun lain. Muat ulang halaman untuk melihat versi terbaru.',
    expired: 'Sesi berakhir. Muat ulang halaman lalu masuk lagi; isian aman di perangkat ini.',
    locked: 'Laporan ini sudah diajukan atau dikunci, sehingga tidak bisa diubah lagi.',
};

// Keadaan yang tidak bisa pulih dengan mencoba lagi; simpan otomatis berhenti.
const STOPPED = ['conflict', 'expired', 'locked'];

/** localStorage bisa tidak tersedia (mode privat, diblokir); semua akses dibungkus try/catch. */
const storage = {
    get(key) {
        try {
            return JSON.parse(window.localStorage.getItem(key));
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch {
            // penuh atau diblokir: tetap lanjut tanpa cadangan
        }
    },
    remove(key) {
        try {
            window.localStorage.removeItem(key);
        } catch {
            // abaikan
        }
    },
};

/** Dipanggil saat keluar: cadangan isian laporan semua akun di perangkat ini dihapus. */
export function clearReportBackups() {
    try {
        Object.keys(window.localStorage)
            .filter((key) => key.startsWith(BACKUP_PREFIX))
            .forEach((key) => window.localStorage.removeItem(key));
    } catch {
        // abaikan
    }
}

function withChoice(item, products) {
    const known = item.product_id && products.some((product) => product.id === item.product_id);

    // Tanpa produk terdaftar, nama selalu ditulis sendiri.
    const fallback = products.length ? '' : 'lain';

    return { ...BLANK_ITEM, ...item, choice: known ? String(item.product_id) : item.product_name ? 'lain' : fallback };
}

/**
 * Form laporan bulanan bertahap (bagian A–H) dengan simpan otomatis dan cadangan di perangkat.
 * Kunci cadangan memuat id akun + id laporan, sehingga hanya ditawarkan ke akun yang sama.
 */
// Simpanan yang sedang berjalan; di luar data Alpine agar Promise tidak dibungkus reaktivitas.
let pending = Promise.resolve(true);

export default ({ state, version, errors, sections, products, previous, attachmentCount, saveUrl, submitFormId, backupKey, interval }) => ({
    data: { ...state, items: state.items.map((item) => withChoice(item, products)) },
    version,
    errors: Array.isArray(errors) ? {} : errors,
    sections,
    products,
    previous,
    section: 'a',
    status: 'idle',
    savedAt: null,
    dirty: false,
    saving: false,
    queued: false,
    submitting: false,
    backup: null,
    copying: null,

    init() {
        this.section = this.firstSectionWithError() ?? this.rememberedSection() ?? 'a';
        this.offerBackup();

        let backupTimer;
        this.$watch('data', () => {
            this.dirty = true;
            if (!this.saving && !STOPPED.includes(this.status)) {
                this.status = 'dirty';
            }
            clearTimeout(backupTimer);
            backupTimer = setTimeout(() => this.writeBackup(), 400);
        });

        this.$watch('section', (key) => {
            this.rememberSection(key);
            this.save();
            this.$nextTick(() => {
                const heading = document.getElementById(`bagian-${key}`);
                heading?.focus({ preventScroll: true });
                window.scrollTo({ top: 0 });
            });
        });

        setInterval(() => this.save(), interval * 1000);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'hidden') {
                this.writeBackup();
                this.save({ keepalive: true });
            }
        });
    },

    // Navigasi bagian ------------------------------------------------------

    get index() {
        return this.sections.findIndex((section) => section.key === this.section);
    },

    go(key) {
        this.section = key;
    },

    next() {
        this.section = this.sections[Math.min(this.index + 1, this.sections.length - 1)].key;
    },

    prev() {
        this.section = this.sections[Math.max(this.index - 1, 0)].key;
    },

    rememberedSection() {
        try {
            const key = window.sessionStorage.getItem(`${backupKey}:bagian`);
            return this.sections.some((section) => section.key === key) ? key : null;
        } catch {
            return null;
        }
    },

    rememberSection(key) {
        try {
            window.sessionStorage.setItem(`${backupKey}:bagian`, key);
        } catch {
            // abaikan
        }
    },

    // Error dan kelengkapan -----------------------------------------------

    error(key) {
        return this.errors[key]?.[0] ?? '';
    },

    sectionOf(key) {
        return SECTION_OF.find(([pattern]) => pattern.test(key))?.[1] ?? null;
    },

    firstSectionWithError() {
        const keys = Object.keys(this.errors);
        return this.sections.map((section) => section.key).find((key) => keys.some((errorKey) => this.sectionOf(errorKey) === key)) ?? null;
    },

    unreadable(value, kind) {
        return Number.isNaN(parseNumber(value, kind));
    },

    /** Kekurangan yang membuat laporan belum bisa diajukan; sama dengan aturan di SubmitReport. */
    problems(key) {
        const data = this.data;
        const required = !data.no_activity;
        const list = [];

        if (key === 'a') {
            if (required && !String(data.activity_summary ?? '').trim()) list.push('Ringkasan aktivitas belum diisi.');
            if (this.unreadable(data.activity_count, 'integer')) list.push('Jumlah kegiatan tidak terbaca.');
        }
        if (key === 'b') {
            data.items.forEach((item, i) => {
                const label = `Produk ${i + 1}`;
                if (!String(item.product_name ?? '').trim()) list.push(`${label}: pilih produk atau tulis namanya.`);
                if (String(item.production_qty).trim() && !String(item.production_unit).trim()) list.push(`${label}: satuan produksi belum diisi.`);
                if (String(item.sales_qty).trim() && !String(item.sales_unit).trim()) list.push(`${label}: satuan penjualan belum diisi.`);
                if (this.unreadable(item.production_qty, 'decimal') || this.unreadable(item.sales_qty, 'decimal') || this.unreadable(item.revenue, 'money')) {
                    list.push(`${label}: ada angka yang tidak terbaca.`);
                }
            });
        }
        if (key === 'c') {
            if (required && !String(data.members_active ?? '').trim()) list.push('Jumlah anggota aktif belum diisi.');
            if (['members_active', 'workforce_count', 'members_change'].some((field) => this.unreadable(data[field], 'integer'))) list.push('Ada angka yang tidak terbaca.');
        }
        if (key === 'd') {
            if (['income_sales', 'income_other', 'costs'].some((field) => this.unreadable(data[field], 'money'))) list.push('Ada angka yang tidak terbaca.');
        }
        if (key === 'e' || key === 'f') {
            const rows = key === 'e' ? data.problems : data.supports;
            rows.forEach((row, i) => {
                if (!row.category) list.push(`Baris ${i + 1}: kategori belum dipilih.`);
                if (row.category === 'lainnya' && !String(row.description ?? '').trim()) list.push(`Baris ${i + 1}: jelaskan kategori "Lainnya".`);
            });
        }

        return list;
    },

    needsAttention(key) {
        return this.problems(key).length > 0 || Object.keys(this.errors).some((errorKey) => this.sectionOf(errorKey) === key);
    },

    /** Ringkasan satu baris per bagian di bagian H. */
    summary(key) {
        const data = this.data;
        const count = (rows, noun) => (rows.length ? `${rows.length} ${noun} dicatat.` : `Tidak ada ${noun} yang dicatat.`);

        return {
            a: data.no_activity ? 'Tidak ada aktivitas bulan ini.' : 'Ringkasan aktivitas sudah diisi.',
            b: data.items.length ? `${data.items.length} produk.` : 'Belum ada produk. Opsional.',
            c: String(data.members_active ?? '').trim() ? `Anggota aktif: ${data.members_active}.` : 'Opsional karena tidak ada aktivitas.',
            d: this.profitText ? `Laba ${this.profitText}.` : 'Laba belum bisa dihitung karena ada angka yang kosong. Laporan tetap bisa diajukan.',
            e: count(data.problems, 'masalah'),
            f: count(data.supports, 'kebutuhan'),
            g: attachmentCount ? `${attachmentCount} file.` : 'Belum ada file. Opsional.',
        }[key];
    },

    get readyToSubmit() {
        return this.sections.every((section) => !this.needsAttention(section.key));
    },

    // Produk ---------------------------------------------------------------

    addItem() {
        this.data.items.push({ ...BLANK_ITEM, choice: this.products.length ? '' : 'lain' });
    },

    removeItem(index) {
        this.data.items.splice(index, 1);
        this.clearErrors('items');
    },

    choose(item) {
        if (item.choice === 'lain' || item.choice === '') {
            item.product_id = null;
            item.product_name = '';
            return;
        }

        const product = this.products.find((candidate) => String(candidate.id) === item.choice);
        item.product_id = product.id;
        item.product_name = product.name;
        item.production_unit ||= product.unit ?? '';
        item.sales_unit ||= product.unit ?? '';
    },

    get revenueTotal() {
        const filled = this.data.items.map((item) => parseNumber(item.revenue, 'money')).filter((value) => value !== null && !Number.isNaN(value));

        return { count: filled.length, total: filled.reduce((sum, value) => sum + value, 0) };
    },

    get revenueTotalText() {
        return formatNumber(this.revenueTotal.total, 'money');
    },

    useRevenueTotal() {
        this.data.income_sales = formatNumber(this.revenueTotal.total);
    },

    get profitText() {
        const [sales, other, costs] = ['income_sales', 'income_other', 'costs'].map((field) => parseNumber(this.data[field], 'money'));

        if ([sales, other, costs].some((value) => value === null || Number.isNaN(value))) {
            return null;
        }

        const profit = sales + other - costs;
        return (profit < 0 ? '-' : '') + formatNumber(Math.abs(profit), 'money');
    },

    // Masalah dan kebutuhan --------------------------------------------------

    addIssue(kind) {
        this.data[kind].push({ ...BLANK_ISSUE });
    },

    removeIssue(kind, index) {
        this.data[kind].splice(index, 1);
        this.clearErrors(kind);
    },

    clearErrors(prefix) {
        this.errors = Object.fromEntries(Object.entries(this.errors).filter(([key]) => !key.startsWith(prefix)));
    },

    // Salin dari bulan lalu ---------------------------------------------------

    copyPrevious(part) {
        const hasData = part === 'items'
            ? this.data.items.length > 0
            : [this.data.members_active, this.data.workforce_count].some((value) => String(value ?? '').trim());

        if (hasData) {
            this.copying = part;
            this.$dispatch('open-modal', 'report-copy');
            return;
        }

        this.applyCopy(part);
    },

    applyCopy(part) {
        if (part === 'items') {
            this.data.items = this.previous.items.map((item) => withChoice(item, this.products));
            this.clearErrors('items');
        } else {
            this.data.members_active = this.previous.members_active;
            this.data.workforce_count = this.previous.workforce_count;
        }

        this.copying = null;
        this.$dispatch('close-modal', 'report-copy');
    },

    // Simpan --------------------------------------------------------------------

    get statusText() {
        if (this.status === 'saved' && !this.dirty) {
            return `Tersimpan pukul ${this.savedAt}`;
        }

        return STATUS_TEXT[this.status] ?? '';
    },

    get statusIsProblem() {
        return ['offline', 'invalid', 'error', ...STOPPED].includes(this.status);
    },

    payload() {
        const items = this.data.items.map(({ choice, ...item }) => item);

        return { ...this.data, items, version: this.version };
    },

    /**
     * Mengirim isian ke server. Hasil true bila tersimpan (atau memang tidak ada perubahan).
     * Bila simpanan lain sedang berjalan, menunggu sampai simpanan berikutnya (dengan isian terbaru) selesai.
     */
    save({ keepalive = false, force = false } = {}) {
        if (STOPPED.includes(this.status)) {
            return Promise.resolve(false);
        }
        if (!this.dirty && !force) {
            return Promise.resolve(true);
        }
        if (this.saving) {
            this.queued = true;
            return pending;
        }

        pending = this.send(keepalive);
        return pending;
    },

    async send(keepalive) {
        this.saving = true;
        this.status = 'saving';
        const snapshot = JSON.stringify(this.data);
        let saved = false;

        try {
            const response = await fetch(saveUrl, {
                method: 'PATCH',
                keepalive,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(this.payload()),
            });
            saved = await this.handleResponse(response, snapshot);
        } catch {
            this.status = 'offline';
        }

        this.saving = false;

        if (this.queued) {
            this.queued = false;
            if (saved && this.dirty) {
                return this.send(false);
            }
        }

        return saved;
    },

    async handleResponse(response, snapshot) {
        if (response.ok) {
            const body = await response.json();
            this.version = body.version;
            this.savedAt = body.saved_at;
            this.errors = {};
            this.status = 'saved';

            // Pengguna bisa saja mengetik lagi selama permintaan berjalan; cadangan baru dihapus bila sama.
            if (JSON.stringify(this.data) === snapshot) {
                this.dirty = false;
                storage.remove(backupKey);
            } else {
                this.status = 'dirty';
            }

            return true;
        }

        if (response.status === 422) {
            const body = await response.json().catch(() => ({}));
            this.errors = body.errors ?? {};
            this.status = 'invalid';
            return false;
        }

        this.status = { 409: 'conflict', 401: 'expired', 419: 'expired', 403: 'locked', 404: 'locked' }[response.status] ?? 'error';
        return false;
    },

    // Cadangan di perangkat ---------------------------------------------------

    writeBackup() {
        if (this.dirty) {
            storage.set(backupKey, { saved_at: Date.now(), data: this.data });
        }
    },

    offerBackup() {
        const backup = storage.get(backupKey);

        if (!backup?.data || JSON.stringify(backup.data) === JSON.stringify(this.data)) {
            storage.remove(backupKey);
            return;
        }

        this.backup = backup;
    },

    get backupTime() {
        return this.backup
            ? new Date(this.backup.saved_at).toLocaleString('id-ID', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
            : '';
    },

    restoreBackup() {
        this.data = { ...this.data, ...this.backup.data };
        this.backup = null;
        this.save({ force: true });
    },

    discardBackup() {
        storage.remove(backupKey);
        this.backup = null;
    },

    // Ajukan --------------------------------------------------------------------

    async submit() {
        this.$dispatch('close-modal', 'report-submit');
        this.submitting = true;

        if (!(await this.save())) {
            this.submitting = false;
            this.section = this.firstSectionWithError() ?? this.section;
            return;
        }

        storage.remove(backupKey);
        document.getElementById(submitFormId).submit();
    },
});
