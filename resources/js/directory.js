/**
 * Direktori publik (form GET #direktori, lihat publik/directory/index.blade.php). Tanpa JS form tetap bekerja biasa.
 * - Perubahan filter/urutan memuat ulang hasil (cari: debounce 300 ms), URL diperbarui (pushState) sehingga bisa
 *   dibagikan dan tombol kembali bekerja. Halaman baru diambil utuh; elemen [data-swap] (menurut id) dan angka
 *   [data-facet] ditukar, lalu isian form disamakan dengan URL.
 * - Tautan chip, paginasi, dan "Hapus semua filter" ([data-directory-link], [data-directory-pages] a) dimuat sama.
 * - Kartu hasil baru muncul bergiliran (fade-up, variabel --reveal-* di app.css); mati bila prefers-reduced-motion.
 * - Tampilan Kartu/Daftar diingat di localStorage (try/catch: penyimpanan bisa diblokir).
 * - Bottom sheet filter (< 1024px): fokus terkunci di dalam, Esc/latar/tombol/geser ke bawah menutup.
 */
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const VIEW_KEY = 'simker:direktori:tampilan';
const SEARCH_DELAY = 300;
const token = (name, fallback) => Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name)) || fallback;
const baseName = (name) => name.replace(/\[\d*\]$/, '');

export default function directory() {
    return {
        view: 'grid',
        sheet: false,
        loading: false,
        drag: 0,
        dragFrom: null,
        opener: null,
        timer: null,
        request: null,

        init() {
            try {
                this.view = window.localStorage.getItem(VIEW_KEY) === 'list' ? 'list' : 'grid';
            } catch {
                // Penyimpanan diblokir: tetap tampilan Kartu.
            }
            this.$watch('view', (value) => {
                try {
                    window.localStorage.setItem(VIEW_KEY, value);
                } catch {
                    // Abaikan.
                }
            });
            this.$watch('sheet', (open) => {
                document.documentElement.classList.toggle('overflow-hidden', open);
                if (open) {
                    this.$nextTick(() => (this.$refs.sheet.querySelector('[data-sheet-first]') ?? this.$refs.sheet).focus());
                } else {
                    this.drag = 0;
                    this.opener?.focus();
                }
            });
            window.addEventListener('popstate', () => this.load(window.location.href, { push: false }));
        },

        url() {
            const params = new URLSearchParams();
            for (const [key, value] of new FormData(this.$refs.form)) {
                if (value !== '' && !(key === 'urut' && value === 'nama')) params.append(key, value);
            }
            const query = params.toString();

            return this.$refs.form.action + (query ? `?${query}` : '');
        },

        submit() {
            clearTimeout(this.timer);
            const url = this.url();
            if (url !== window.location.href) this.load(url);
        },

        typed() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.submit(), SEARCH_DELAY);
        },

        clearFilters() {
            for (const input of this.$refs.form.querySelectorAll('input[type=checkbox]')) input.checked = false;
            this.submit();
        },

        navigate(event) {
            const link = event.target.closest('a[data-directory-link], [data-directory-pages] a');
            if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            this.load(link.href, { scroll: link.closest('[data-directory-pages]') !== null });
        },

        async load(url, { push = true, scroll = false } = {}) {
            this.request?.abort();
            const request = (this.request = new AbortController());
            this.loading = true;
            try {
                const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: request.signal });
                if (!response.ok) throw new Error(String(response.status));
                const next = new DOMParser().parseFromString(await response.text(), 'text/html');

                for (const region of this.$root.querySelectorAll('[data-swap]')) {
                    const fresh = next.getElementById(region.id);
                    if (fresh) region.innerHTML = fresh.innerHTML;
                }
                for (const count of this.$root.querySelectorAll('[data-facet]')) {
                    const fresh = next.querySelector(`[data-facet="${count.dataset.facet}"]`);
                    if (!fresh) continue;
                    count.innerHTML = fresh.innerHTML;
                    const option = count.closest('[data-empty]');
                    if (option) option.dataset.empty = fresh.closest('[data-empty]')?.dataset.empty ?? 'false';
                }
                document.title = next.title;
                for (const selector of ['meta[name="description"]', 'link[rel="canonical"]', 'meta[property="og:title"]', 'meta[property="og:description"]']) {
                    const [current, fresh] = [document.head.querySelector(selector), next.head.querySelector(selector)];
                    if (current && fresh) current.replaceWith(fresh.cloneNode());
                }

                if (push) window.history.pushState({}, '', url);
                this.sync(url);
                if (scroll) this.$refs.results.scrollIntoView({ behavior: reduce.matches ? 'auto' : 'smooth', block: 'start' });
                // Tunggu Alpine memasang direktif pada isi baru, lalu mainkan kartu.
                setTimeout(() => this.animate());
            } catch (error) {
                if (error.name !== 'AbortError') window.location.href = url;
            } finally {
                if (this.request === request) this.loading = false;
            }
        },

        // Isian form mengikuti URL (chip, paginasi, tombol kembali); kotak cari yang sedang diketik tidak ditimpa.
        sync(url) {
            const params = new URL(url, window.location.href).searchParams;
            const values = {};
            for (const [key, value] of params) (values[baseName(key)] ??= []).push(value);
            for (const input of this.$refs.form.elements) {
                if (!input.name) continue;
                const current = values[baseName(input.name)] ?? [];
                if (input.type === 'checkbox') input.checked = current.includes(input.value);
                else if (input.tagName === 'SELECT') input.value = current[0] ?? input.options[0].value;
                else if (input.type === 'search' && document.activeElement !== input) input.value = current[0] ?? '';
            }
        },

        animate() {
            const cards = [...(this.$root.querySelector('[data-cards]')?.children ?? [])];
            if (reduce.matches || cards.length === 0) return;
            const step = token('--reveal-stagger', 150) / 2;
            for (const [i, card] of cards.entries()) {
                card.style.transition = 'none';
                card.dataset.reveal = 'fade-up';
                card.dataset.revealState = 'hidden';
                card.style.setProperty('--reveal-delay', `${Math.min(i, 5) * step}ms`);
            }
            void document.body.offsetHeight;
            requestAnimationFrame(() => {
                for (const card of cards) {
                    card.style.transition = '';
                    card.dataset.revealState = 'shown';
                }
            });
        },

        open(event) {
            this.opener = event.currentTarget;
            this.sheet = true;
        },

        close() {
            if (this.sheet) this.sheet = false;
        },

        trap(event) {
            if (!this.sheet) return;
            const focusable = [...this.$refs.sheet.querySelectorAll('a[href], button, input, select, summary, [tabindex]:not([tabindex="-1"])')]
                .filter((el) => !el.disabled && el.offsetParent !== null);
            const [first, last] = [focusable[0], focusable.at(-1)];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },

        dragStart(event) {
            this.dragFrom = event.touches[0].clientY;
        },

        dragMove(event) {
            if (this.dragFrom !== null) this.drag = Math.max(0, event.touches[0].clientY - this.dragFrom);
        },

        dragEnd() {
            if (this.drag > 80) this.close();
            this.drag = 0;
            this.dragFrom = null;
        },
    };
}
