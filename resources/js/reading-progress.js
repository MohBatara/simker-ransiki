/**
 * Garis progres membaca di detail berita: lebar (scaleX) = bagian artikel [data-print-article] yang sudah dilewati,
 * dari puncak artikel sampai dasarnya di bawah layar. Dihitung saat digulir lewat requestAnimationFrame, tanpa transisi
 * CSS sehingga tetap patuh prefers-reduced-motion (garis tetap tampil, hanya mengikuti posisi gulir).
 */
export default function readingProgress() {
    return {
        init() {
            const article = document.querySelector('[data-print-article]');
            if (!article) return;
            let frame = null;
            const update = () => {
                frame = null;
                const rect = article.getBoundingClientRect();
                const total = rect.height - window.innerHeight;
                const progress = total <= 0 ? 1 : Math.min(1, Math.max(0, -rect.top / total));
                this.$refs.bar.style.transform = `scaleX(${progress.toFixed(4)})`;
            };
            const schedule = () => {
                if (frame === null) frame = requestAnimationFrame(update);
            };
            window.addEventListener('scroll', schedule, { passive: true });
            window.addEventListener('resize', schedule, { passive: true });
            this.$nextTick(update);
        },
    };
}
