/**
 * Deretan kartu yang digeser (scroll-snap), aktif hanya selama deretan memang bisa digeser (< 1024px):
 * - `active` mengikuti titik snap terdekat (kartu terakhir saat sudah di ujung); titik bisa diketuk untuk menggeser.
 * - Kartu yang utuh terlihat (di posisi snap) skala 1 dan opacity 1; kartu yang terpotong mengecil halus sampai 0,94
 *   dan 0,7 menurut bagiannya yang tersembunyi, mengikuti posisi geser. Diukur dari bagian terlihat, bukan jarak ke
 *   titik snap, agar di tablet (dua kartu utuh) keduanya tetap penuh. Dipasang pada elemen pertama di dalam tiap item
 *   agar tidak bentrok dengan transform x-reveal pada item.
 * - Isyarat geser sekali per sesi setelah kartu selesai masuk; batal bila deretan sudah disentuh atau digeser.
 * Tanpa efek bila prefers-reduced-motion. Elemen deretan: x-ref="track". Opsi { focus: false } untuk strip.
 */
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const MIN_SCALE = 0.94;
const MIN_OPACITY = 0.7;

const token = (name, fallback) => Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name)) || fallback;
const seconds = (value) => Math.max(...value.split(',').map((part) => Number.parseFloat(part) * (part.trim().endsWith('ms') ? 1 : 1000)));

function remember(key) {
    try {
        if (window.sessionStorage.getItem(key)) return true;
        window.sessionStorage.setItem(key, '1');
    } catch {
        // Penyimpanan diblokir: isyarat boleh tampil lagi, tidak masalah.
    }

    return false;
}

export default function scrollDots({ focus = true } = {}) {
    return {
        active: 0,
        snaps: [],
        frame: null,
        touched: false,
        hint: null,

        init() {
            const track = this.$refs.track;
            const schedule = () => {
                if (this.frame === null) this.frame = requestAnimationFrame(() => this.update());
            };
            const touch = () => {
                this.touched = true;
                this.hint?.forEach((animation) => animation.cancel());
            };

            track.addEventListener('scroll', schedule, { passive: true });
            ['pointerdown', 'touchstart', 'wheel'].forEach((type) => track.addEventListener(type, touch, { passive: true }));
            // Event scroll juga muncul tanpa gerakan (lebar isi berubah saat kartu masuk); hanya geser sungguhan yang dihitung.
            track.addEventListener('scroll', () => track.scrollLeft > 2 && touch(), { passive: true });
            window.addEventListener('resize', () => {
                this.measure();
                schedule();
            }, { passive: true });

            // Kartu selesai masuk (x-reveal) → isyarat geser. Sudah tampil saat dibuka → isyarat setelah jeda singkat.
            let waiting = null;
            track.addEventListener('reveal:shown', () => {
                clearTimeout(waiting);
                waiting = setTimeout(() => this.nudge(), this.entranceTime());
            });
            // Setelah x-reveal pada anak-anaknya terpasang.
            requestAnimationFrame(() => {
                this.measure();
                this.update();
                if ([...track.children].every((item) => item.dataset.revealState === 'shown') && track.getBoundingClientRect().top < window.innerHeight) {
                    waiting = setTimeout(() => this.nudge(), 1200);
                }
            });
        },

        scrollable() {
            const track = this.$refs.track;

            return ['auto', 'scroll'].includes(getComputedStyle(track).overflowX) && track.scrollWidth > track.clientWidth + 2;
        },

        // Posisi snap tiap kartu (dibatasi ujung geser); dibaca ulang hanya saat ukuran berubah.
        measure() {
            const track = this.$refs.track;
            const padding = Number.parseFloat(getComputedStyle(track).scrollPaddingLeft) || 0;
            const max = track.scrollWidth - track.clientWidth;
            this.padding = padding;
            this.boxes = [...track.children].map((item) => [item.offsetLeft, item.offsetWidth]);
            this.snaps = this.boxes.map(([left]) => Math.min(Math.max(left - padding, 0), max));
            this.max = max;
        },

        update() {
            this.frame = null;
            const track = this.$refs.track;
            const x = track.scrollLeft;
            const items = [...track.children];

            let nearest = 0;
            this.snaps.forEach((snap, i) => {
                if (Math.abs(snap - x) <= Math.abs(this.snaps[nearest] - x)) nearest = i;
            });
            this.active = x >= this.max - 2 ? items.length - 1 : nearest;

            if (!focus) return;
            const on = !reduce.matches && this.scrollable();
            items.forEach((item, i) => {
                const card = item.firstElementChild;
                if (!card) return;
                if (!on) {
                    card.style.transform = card.style.opacity = card.style.willChange = '';

                    return;
                }
                // Bagian kartu di antara tepi kiri area snap dan tepi kanan deretan; terpotong ≥ 80% = paling kecil.
                const [left, width] = this.boxes[i] ?? [0, 1];
                const visible = Math.max(0, Math.min(left + width - x, track.clientWidth) - Math.max(left - x, this.padding)) / width;
                const raw = Math.min(1, Math.max(0, (1 - visible) / 0.8));
                const distance = raw < 0.01 ? 0 : raw;
                card.style.willChange = 'transform, opacity';
                card.style.transform = distance ? `scale(${(1 - (1 - MIN_SCALE) * distance).toFixed(4)})` : '';
                card.style.opacity = distance ? (1 - (1 - MIN_OPACITY) * distance).toFixed(3) : '';
            });
        },

        entranceTime() {
            const items = [...this.$refs.track.children].slice(0, 6);

            return Math.max(0, ...items.map((item) => {
                const style = getComputedStyle(item);

                return (Number.parseFloat(item.style.getPropertyValue('--reveal-delay')) || 0) + seconds(style.transitionDuration);
            }));
        },

        nudge() {
            const track = this.$refs.track;
            if (this.touched || this.hint || reduce.matches || !this.scrollable() || track.scrollLeft > 2) return;
            const owner = track.closest('[aria-labelledby], [aria-label]');
            if (remember(`simker:isyarat-geser:${owner?.getAttribute('aria-labelledby') ?? owner?.getAttribute('aria-label') ?? 'deretan'}`)) return;

            // Snap dimatikan selama isyarat; tanpa itu browser ikut menggulir mengejar kartu yang bergeser.
            const distance = token('--row-hint-distance', 28);
            const snapType = track.style.scrollSnapType;
            track.style.scrollSnapType = 'none';
            this.hint = [...track.children].map((item) => item.animate(
                [{ transform: 'translate3d(0, 0, 0)' }, { transform: `translate3d(-${distance}px, 0, 0)`, offset: 0.45 }, { transform: 'translate3d(0, 0, 0)' }],
                { duration: token('--row-hint-duration', 900), easing: 'cubic-bezier(0.45, 0, 0.25, 1)' },
            ));
            const restore = () => (track.style.scrollSnapType = snapType);
            this.hint[0]?.finished.then(restore, restore);
        },

        go(index) {
            const left = this.snaps[index] ?? 0;
            this.$refs.track.scrollTo({ left, behavior: reduce.matches ? 'auto' : 'smooth' });
        },
    };
}
