/**
 * Navigasi dalam halaman (Profil Ransiki): bar tautan #bagian yang menempel di bawah header.
 * - `active` = bagian terakhir yang judulnya sudah melewati bar (bagian terakhir bila halaman sudah di dasar).
 * - Klik menggulir halus ke bagian (langsung bila prefers-reduced-motion), lalu fokus pindah ke bagian itu.
 * - Di HP bar bisa digeser; tautan aktif digeser ke tengah bar. Elemen deretan: x-ref="track".
 */
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const behavior = () => (reduce.matches ? 'auto' : 'smooth');

export default function sectionNav() {
    return {
        active: null,
        links: [],

        init() {
            this.links = [...this.$el.querySelectorAll('a[href^="#"]')];
            this.active = this.id(this.links[0]);

            let ticking = false;
            const schedule = () => {
                if (!ticking) {
                    ticking = true;
                    requestAnimationFrame(() => {
                        ticking = false;
                        this.update();
                    });
                }
            };
            window.addEventListener('scroll', schedule, { passive: true });
            window.addEventListener('resize', schedule, { passive: true });
            this.$watch('active', (id) => this.center(id));
            this.update();
        },

        id(link) {
            return link ? decodeURIComponent(link.hash.slice(1)) : null;
        },

        update() {
            const line = this.$el.getBoundingClientRect().bottom + 24;
            let current = this.id(this.links[0]);
            for (const link of this.links) {
                const section = document.getElementById(this.id(link));
                if (section && section.getBoundingClientRect().top <= line) current = section.id;
            }
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
                current = this.id(this.links.at(-1));
            }
            this.active = current;
        },

        center(id) {
            const track = this.$refs.track;
            const link = this.links.find((item) => this.id(item) === id);
            if (!track || !link || track.scrollWidth <= track.clientWidth) return;
            track.scrollTo({ left: link.offsetLeft - (track.clientWidth - link.offsetWidth) / 2, behavior: behavior() });
        },

        go(event) {
            const section = document.getElementById(this.id(event.currentTarget));
            if (!section) return;
            event.preventDefault();
            section.scrollIntoView({ behavior: behavior(), block: 'start' });
            history.replaceState(null, '', `#${section.id}`);
            section.focus({ preventScroll: true });
        },
    };
}
