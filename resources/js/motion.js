/**
 * Efek scroll website publik (satu sistem). Semua mati bila prefers-reduced-motion atau tanpa IntersectionObserver;
 * konten selalu dirender tampil oleh server, kelas penyembunyi baru dipasang di sini.
 *
 * x-reveal:fade-up | fade-in | scale-in | slide-left       satu elemen
 * x-reveal:stagger.<varian>.<jeda ms>.<tunda awal ms>       anak elemen bergiliran (maks 6 yang dijeda)
 * x-count.<tunda ms>                                         angka menghitung naik sekali saat terlihat
 * x-parallax="<px>"                                          geser vertikal halus selama elemen di layar (≥ 768px)
 * $store.scroll.pastHeader / pastFold                        posisi gulir (header ringkas, tombol kembali ke atas)
 */
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const desktop = window.matchMedia('(min-width: 48rem)');
const STAGGER_MAX = 6;

// Elemen yang sudah terlihat (atau sudah lewat di atas layar) saat dibuka tidak disembunyikan lalu dimunculkan.
const seenOnLoad = (el) => el.getBoundingClientRect().top < window.innerHeight;

const observer = 'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                observer.unobserve(entry.target);
                entry.target._onVisible?.();
            }
        }
    }, { threshold: 0, rootMargin: '0px 0px -15% 0px' })
    : null;

function reveal(el, { value, modifiers }) {
    if (reduce.matches || !observer || seenOnLoad(el)) {
        return;
    }

    const stagger = value === 'stagger';
    const variant = (stagger ? modifiers.find((m) => Number.isNaN(Number(m))) : value) || 'fade-up';
    const [step = 70, base = 0] = modifiers.filter((m) => !Number.isNaN(Number(m))).map(Number);
    const targets = stagger ? [...el.children] : [el];

    targets.forEach((target, i) => {
        target.dataset.reveal = variant;
        target.dataset.revealState = 'hidden';
        target.style.setProperty('--reveal-delay', `${base + (stagger ? Math.min(i, STAGGER_MAX - 1) * step : 0)}ms`);
    });

    el._onVisible = () => targets.forEach((target) => {
        target.dataset.revealState = 'shown';
        // Setelah selesai, atribut dibuang agar transform hover/tautan berjalan normal.
        target.addEventListener('transitionend', () => {
            delete target.dataset.reveal;
            delete target.dataset.revealState;
            target.style.removeProperty('--reveal-delay');
        }, { once: true });
    });
    observer.observe(el);
}

function count(el, { modifiers }) {
    const finalText = el.textContent.trim();
    const target = Number.parseInt(finalText.replace(/\D/g, ''), 10);

    if (reduce.matches || !observer || !target || el.getBoundingClientRect().bottom < 0) {
        return;
    }

    // Pembaca layar membaca nilai akhir; lebar dikunci agar tidak ada pergeseran tata letak.
    const sr = document.createElement('span');
    sr.className = 'sr-only';
    sr.textContent = finalText;
    el.after(sr);
    el.setAttribute('aria-hidden', 'true');
    el.style.display = 'inline-block';
    el.style.minWidth = `${el.getBoundingClientRect().width}px`;
    el.textContent = '0';

    const format = new Intl.NumberFormat('id-ID');
    const delay = Number(modifiers.find((m) => !Number.isNaN(Number(m))) ?? 0);
    const run = () => setTimeout(() => {
        const start = performance.now();
        const frame = (now) => {
            const t = Math.min(1, (now - start) / 800);
            el.textContent = format.format(Math.round(target * (1 - (1 - t) ** 3)));
            if (t < 1) {
                requestAnimationFrame(frame);
            } else {
                el.textContent = finalText;
            }
        };
        requestAnimationFrame(frame);
    }, delay);

    if (seenOnLoad(el)) {
        run();
    } else {
        el._onVisible = run;
        observer.observe(el);
    }
}

function parallax(el, { expression }) {
    if (reduce.matches) {
        return;
    }

    const max = Number(expression) || 40;
    let ticking = false;
    const update = () => {
        ticking = false;
        const rect = el.parentElement.getBoundingClientRect();
        const progress = desktop.matches && rect.bottom > 0 ? Math.min(1, Math.max(0, -rect.top / rect.height)) : 0;
        el.style.transform = progress ? `translate3d(0, ${(progress * max).toFixed(1)}px, 0)` : '';
    };

    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });
    desktop.addEventListener('change', update);
}

export default function registerMotion(Alpine) {
    Alpine.directive('reveal', reveal);
    Alpine.directive('count', count);
    Alpine.directive('parallax', parallax);

    // Satu pendengar gulir untuk semua penanda posisi; nilai hanya diubah saat berganti (bukan tiap frame).
    Alpine.store('scroll', { pastHeader: false, pastFold: false });
    const store = Alpine.store('scroll');
    let ticking = false;
    const update = () => {
        ticking = false;
        const y = window.scrollY;
        if (store.pastHeader !== y > 24) store.pastHeader = y > 24;
        if (store.pastFold !== y > window.innerHeight * 2) store.pastFold = y > window.innerHeight * 2;
    };
    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            requestAnimationFrame(update);
        }
    }, { passive: true });
    update();
}
