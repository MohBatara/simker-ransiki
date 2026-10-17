/**
 * Katalog dan detail produk publik (publik/products/*).
 * - scrollRow: panah deretan geser "Pilihan distrik" (scroll-snap); panah nonaktif di ujung. Elemen deretan: x-ref="track".
 * - productGallery: thumbnail mengganti foto utama; tampilan perbesar memakai <dialog> (Esc, tombol, atau latar menutup;
 *   panah kiri/kanan berpindah foto). Tanpa JS foto utama tetap tampil.
 * - stickyCta: di HP bar tombol utama menempel di bawah layar setelah tombol aslinya tergulir lewat atas, dan hilang
 *   lagi saat footer terlihat. Tombol "kembali ke atas" naik selama bar tampil ($store.scroll.bar).
 */
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const behavior = () => (reduce.matches ? 'auto' : 'smooth');

export function scrollRow() {
    return {
        atStart: true,
        atEnd: false,

        init() {
            const track = this.$refs.track;
            const update = () => {
                this.atStart = track.scrollLeft <= 2;
                this.atEnd = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
            };
            track.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update, { passive: true });
            requestAnimationFrame(update);
        },

        // Geser satu "halaman" kartu yang utuh terlihat; snap merapikan posisi akhirnya.
        move(direction) {
            const track = this.$refs.track;
            const item = track.firstElementChild;
            const gap = Number.parseFloat(getComputedStyle(track).columnGap) || 0;
            const step = item ? item.offsetWidth + gap : track.clientWidth;
            const visible = Math.max(1, Math.floor((track.clientWidth + gap) / step));
            track.scrollBy({ left: direction * visible * step, behavior: behavior() });
        },
    };
}

export function productGallery(count) {
    return {
        current: 0,
        count,

        show(index) {
            this.current = (index + this.count) % this.count;
        },

        open() {
            this.$refs.lightbox.showModal();
        },

        close() {
            this.$refs.lightbox.close();
        },

        // Klik di luar foto (latar dialog) menutup.
        backdrop(event) {
            if (event.target === this.$refs.lightbox) this.close();
        },

        key(event) {
            if (this.count < 2) return;
            if (event.key === 'ArrowLeft') this.show(this.current - 1);
            if (event.key === 'ArrowRight') this.show(this.current + 1);
        },
    };
}

export function stickyCta() {
    return {
        shown: false,

        init() {
            // x-ref di dalam komponen baru terdaftar setelah init().
            this.$nextTick(() => this.observe());
        },

        // Dihitung saat digulir (bukan IntersectionObserver: lompatan gulir bisa melewati tombol tanpa pernah memicunya).
        observe() {
            const target = this.$refs.cta;
            const footer = document.querySelector('footer');
            if (!target) return;
            let frame = null;
            const update = () => {
                frame = null;
                const past = target.getBoundingClientRect().bottom < 0;
                const footerVisible = footer ? footer.getBoundingClientRect().top < window.innerHeight : false;
                this.shown = past && !footerVisible;
                this.$store.scroll.bar = this.shown;
            };
            const schedule = () => {
                if (frame === null) frame = requestAnimationFrame(update);
            };
            window.addEventListener('scroll', schedule, { passive: true });
            window.addEventListener('resize', schedule, { passive: true });
            update();
        },
    };
}
