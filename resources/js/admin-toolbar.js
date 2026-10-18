/**
 * Bilah alat daftar Admin/OPD (x-admin.toolbar): filter langsung diterapkan tanpa tombol Terapkan. Hasil dimuat ulang
 * lewat fetch lalu setiap elemen [data-swap="kunci"] di halaman ditukar dengan versi baru dari server (tabel, ringkasan,
 * kartu angka, tautan unduh yang ikut filter). URL diperbarui (pushState); tombol kembali memuat ulang halaman.
 * Cari: jeda 300 ms setelah berhenti mengetik. Gagal memuat = pindah halaman biasa.
 */
const desktopQuery = window.matchMedia('(min-width: 48rem)');

export default () => ({
    open: false,
    loading: false,
    desktop: desktopQuery.matches,
    timer: null,
    controller: null,

    init() {
        desktopQuery.addEventListener('change', (event) => {
            this.desktop = event.matches;
        });
        this.$watch('open', (open) => {
            document.documentElement.classList.toggle('overflow-hidden', open);
            if (open) {
                this.$nextTick(() => this.focusables()[0]?.focus());
            }
        });
        if (!window.__adminToolbarPopstate) {
            window.__adminToolbarPopstate = true;
            window.addEventListener('popstate', (event) => event.state?.adminToolbar && window.location.reload());
        }
    },

    typed() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.apply(), 300);
    },

    url() {
        const params = new URLSearchParams();
        for (const [name, value] of new FormData(this.$refs.form)) {
            if (String(value).trim() !== '') {
                params.append(name, value);
            }
        }
        const query = params.toString();

        return this.$refs.form.action + (query ? `?${query}` : '');
    },

    async apply() {
        clearTimeout(this.timer);
        const url = this.url();
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;

        try {
            const response = await fetch(url, { headers: { Accept: 'text/html' }, signal: this.controller.signal });
            if (!response.ok) {
                window.location.href = url;
                return;
            }
            const fresh = new DOMParser().parseFromString(await response.text(), 'text/html');
            document.querySelectorAll('[data-swap]').forEach((element) => {
                const next = fresh.querySelector(`[data-swap="${element.dataset.swap}"]`);
                if (next) {
                    element.replaceWith(next);
                }
            });
            window.history.pushState({ adminToolbar: true }, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.location.href = url;
            }
        } finally {
            this.loading = false;
        }
    },

    focusables() {
        return [...this.$refs.panel.querySelectorAll('a[href], button:not([disabled]), select:not([disabled]), input:not([disabled])')]
            .filter((element) => element.offsetParent !== null);
    },

    trap(event) {
        const items = this.focusables();
        if (!items.length) {
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    },
});
