/**
 * Slider foto hero beranda. Ganti otomatis setiap `interval` ms; berhenti saat kursor di atasnya, saat ada
 * fokus keyboard di dalamnya, saat tab tersembunyi, dan sama sekali bila pengguna memilih gerak dikurangi.
 */
export default function heroSlider(count, interval = 7000) {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    return {
        active: 0,
        count,
        hovered: false,
        focused: false,
        timer: null,

        init() {
            if (this.count < 2) {
                return;
            }
            this.timer = setInterval(() => {
                if (!this.hovered && !this.focused && !reducedMotion.matches && !document.hidden) {
                    this.go(this.active + 1);
                }
            }, interval);
        },

        destroy() {
            clearInterval(this.timer);
        },

        go(index) {
            this.active = (index + this.count) % this.count;
        },

        next() {
            this.go(this.active + 1);
        },

        prev() {
            this.go(this.active - 1);
        },

        // Fokus berpindah di dalam slider tidak dihitung sebagai keluar.
        blur(event) {
            this.focused = this.$root.contains(event.relatedTarget);
        },
    };
}
