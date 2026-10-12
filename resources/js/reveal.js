/**
 * Anak-anak elemen muncul bertahap (fade + naik 12px, jeda `step` ms) saat elemen masuk layar.
 * Tanpa IntersectionObserver atau bila gerak dikurangi, tidak melakukan apa pun (isi langsung tampil).
 */
export default function reveal(step = 60) {
    return {
        init() {
            if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            const items = [...this.$el.children];
            items.forEach((item, i) => {
                item.dataset.reveal = 'hidden';
                item.style.transitionDelay = `${i * step}ms`;
            });

            const observer = new IntersectionObserver((entries) => {
                if (!entries.some((entry) => entry.isIntersecting)) {
                    return;
                }
                observer.disconnect();
                items.forEach((item) => (item.dataset.reveal = 'shown'));
                // Jeda bertahap hanya untuk kemunculan pertama.
                setTimeout(() => items.forEach((item) => item.style.removeProperty('transition-delay')), items.length * step + 450);
                // Ambang 0 + margin bawah: tetap terpicu walau daftarnya jauh lebih tinggi dari layar (HP).
            }, { threshold: 0, rootMargin: '0px 0px -10% 0px' });

            observer.observe(this.$el);
        },
    };
}
