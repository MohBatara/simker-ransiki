/**
 * Direktori publik (form GET, lihat publik/directory/index.blade.php); dipakai juga katalog Produk
 * (publik/products/index.blade.php: pil bidang berupa radio, urutan bawaan di data-default-sort). Tanpa JS form tetap
 * bekerja biasa.
 * - Perubahan filter/urutan memuat ulang hasil (cari: debounce 300 ms), URL diperbarui (pushState) sehingga bisa
 *   dibagikan dan tombol kembali bekerja. Halaman baru diambil utuh; elemen [data-swap] (menurut id) dan angka
 *   [data-facet] ditukar, lalu isian form disamakan dengan URL.
 * - Tautan chip, paginasi, dan "Hapus semua filter" ([data-directory-link], [data-directory-pages] a) dimuat sama.
 * - Efek ringan (angka di variabel --swap-*, --chip-*, --sheet-*, --reveal-quick-* di app.css): hasil lama memudar,
 *   hasil baru muncul bergiliran, pil filter masuk/keluar dengan skala, bottom sheet meluncur dari bawah. Posisi gulir
 *   dijaga, kecuali pindah halaman (gulir halus ke bar hasil). prefers-reduced-motion: semua langsung tampil.
 * - Tampilan Kartu/Daftar diingat di localStorage (try/catch: penyimpanan bisa diblokir).
 * - Bottom sheet filter (< 1024px): fokus terkunci di dalam, Esc/latar/tombol/geser ke bawah menutup.
 */
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const VIEW_KEY = 'simker:direktori:tampilan';
const SEARCH_DELAY = 300;
const token = (name, fallback) => Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name)) || fallback;
const baseName = (name) => name.replace(/\[\d*\]$/, '');
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const EASE = 'cubic-bezier(0.16, 1, 0.3, 1)';

export default function directory() {
    return {
        view: 'grid',
        sheet: false,
        loading: false,
        drag: 0,
        dragFrom: null,
        opener: null,
        closing: false,
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
                    this.$nextTick(() => {
                        this.slide(true);
                        (this.$refs.sheet.querySelector('[data-sheet-first]') ?? this.$refs.sheet).focus();
                    });
                } else {
                    this.drag = 0;
                    this.opener?.focus();
                }
            });
            window.addEventListener('popstate', () => this.load(window.location.href, { push: false }));
        },

        url() {
            const params = new URLSearchParams();
            const defaultSort = this.$refs.form.dataset.defaultSort ?? 'nama';
            for (const [key, value] of new FormData(this.$refs.form)) {
                if (value !== '' && !(key === 'urut' && value === defaultSort)) params.append(key, value);
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
            const animated = !reduce.matches;
            const results = this.$refs.hasil;
            const y = window.scrollY;
            this.loading = true;
            try {
                // Hasil lama memudar dan pil yang dihapus mengecil, bersamaan dengan pengambilan halaman baru.
                const chips = this.chipKeys();
                const fade = animated ? Promise.all([this.fadeOut(results), this.chipsOut(url)]) : Promise.resolve();
                const response = await fetch(url, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, signal: request.signal });
                if (!response.ok) throw new Error(String(response.status));
                const html = await response.text();
                await fade;
                if (request.signal.aborted) return;
                const next = new DOMParser().parseFromString(html, 'text/html');

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
                results.style.transition = 'none';
                results.style.opacity = '';
                if (scroll) {
                    this.$refs.results.scrollIntoView({ behavior: animated ? 'smooth' : 'auto', block: 'start' });
                } else {
                    window.scrollTo(0, y);
                }
                if (animated) {
                    this.chipsIn(chips);
                    // Tunggu Alpine memasang x-reveal pada kartu baru, lalu mainkan yang terlihat.
                    setTimeout(() => this.animate());
                }
            } catch (error) {
                results.style.opacity = '';
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
                else if (input.type === 'radio') input.checked = current.length ? current.includes(input.value) : input.value === '';
                else if (input.tagName === 'SELECT') input.value = current[0] ?? input.options[0].value;
                else if (input.type === 'search' && document.activeElement !== input) input.value = current[0] ?? '';
            }
        },

        fadeOut(element) {
            const duration = token('--swap-out-duration', 150);
            element.style.transition = `opacity ${duration}ms ease-out`;
            element.style.opacity = '0';

            return wait(duration);
        },

        chipKeys() {
            return new Set([...this.$root.querySelectorAll('[data-chip]')].map((chip) => chip.dataset.chip));
        },

        chipStyle(chip, shown) {
            chip.style.transition = `transform ${token('--chip-duration', 150)}ms ease-out, opacity ${token('--chip-duration', 150)}ms ease-out`;
            chip.style.transform = shown ? '' : 'scale(0.9)';
            chip.style.opacity = shown ? '' : '0';
        },

        // Pil yang tidak ada lagi di URL tujuan (kunci "dimensi-id") mengecil dan memudar sebelum ditukar.
        async chipsOut(url) {
            const kept = new Set();
            for (const [key, value] of new URL(url, window.location.href).searchParams) kept.add(`${baseName(key)}-${value}`);
            const leaving = [...this.$root.querySelectorAll('[data-chip]')].filter((chip) => !kept.has(chip.dataset.chip));
            if (leaving.length === 0) return;
            leaving.forEach((chip) => this.chipStyle(chip, false));
            await wait(token('--chip-duration', 150));
        },

        // Pil baru mulai dari 0,9 dan transparan, lalu membesar ke ukuran penuh.
        chipsIn(before) {
            const entering = [...this.$root.querySelectorAll('[data-chip]')].filter((chip) => !before.has(chip.dataset.chip));
            for (const chip of entering) {
                chip.style.transition = 'none';
                chip.style.transform = 'scale(0.9)';
                chip.style.opacity = '0';
            }
            void document.body.offsetHeight;
            requestAnimationFrame(() => entering.forEach((chip) => this.chipStyle(chip, true)));
        },

        // Kartu baru yang terlihat muncul bergiliran (--swap-stagger); kartu di bawah layar menunggu x-reveal.quick.
        animate() {
            const cards = [...(this.$root.querySelector('[data-cards]')?.children ?? [])]
                .filter((card) => card.getBoundingClientRect().top < window.innerHeight);
            if (cards.length === 0) return;
            const step = token('--swap-stagger', 40);
            for (const [i, card] of cards.entries()) {
                card.style.transition = 'none';
                card.dataset.reveal = 'fade-up';
                card.dataset.revealQuick = '';
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

        // Bottom sheet meluncur dari bawah, latar memudar (Web Animations; langsung bila gerak dikurangi).
        slide(open, from = 0) {
            if (reduce.matches || !this.$refs.sheet.animate) return Promise.resolve();
            const options = { duration: token('--sheet-duration', 250), easing: open ? EASE : 'ease-in', fill: 'none' };
            const frames = open ? [{ transform: 'translateY(100%)' }, { transform: 'translateY(0)' }] : [{ transform: `translateY(${from}px)` }, { transform: 'translateY(100%)' }];
            this.$refs.backdrop.animate([{ opacity: open ? 0 : 1 }, { opacity: open ? 1 : 0 }], options);

            return this.$refs.sheet.animate(frames, options).finished.catch(() => {});
        },

        open(event) {
            this.opener = event.currentTarget;
            this.sheet = true;
        },

        async close() {
            if (!this.sheet || this.closing) return;
            this.closing = true;
            const from = this.drag;
            this.drag = 0;
            // Tetap di posisi geser selama animasi keluar; hanya di HP/tablet (di desktop panel bukan sheet).
            if (window.matchMedia('(max-width: 63.98rem)').matches) await this.slide(false, from);
            this.sheet = false;
            this.closing = false;
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
            this.dragFrom = null;
            if (this.drag > 80) {
                this.close();
            } else {
                this.drag = 0;
            }
        },
    };
}
