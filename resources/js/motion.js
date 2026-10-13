/**
 * Efek scroll website publik (satu sistem). Semua mati bila prefers-reduced-motion atau tanpa IntersectionObserver;
 * konten selalu dirender tampil oleh server, kelas penyembunyi baru dipasang di sini. Jarak, skala, durasi, easing,
 * dan jeda stagger diatur di SATU tempat: variabel --reveal-* di resources/css/app.css.
 *
 * x-reveal="fade-up | fade-left | fade-right | zoom-in"      satu elemen (data-reveal-delay="ms" opsional)
 * x-reveal.stagger="fade-up"                                 anak elemen bergiliran (data-reveal-delay = jeda per item)
 * x-count.<tunda ms>                                         angka menghitung naik setiap kali masuk dari bawah
 * x-parallax="<px>"                                          geser vertikal halus selama elemen di layar (≥ 768px)
 * $store.scroll.pastHeader / pastFold                        posisi gulir (header ringkas, tombol kembali ke atas)
 *
 * Berulang: muncul saat masuk dari bawah, kembali tersembunyi hanya bila keluar lewat BAWAH (pengunjung menggulir naik
 * melewatinya), tetap tampil bila keluar lewat atas.
 */
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const desktop = window.matchMedia('(min-width: 48rem)');
const STAGGER_MAX = 6;
const staggerStep = () => Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--reveal-stagger')) || 120;

// Elemen yang sudah terlihat (atau sudah lewat di atas layar) saat dibuka tidak disembunyikan.
const seenOnLoad = (el) => el.getBoundingClientRect().top < window.innerHeight;

const supported = 'IntersectionObserver' in window;
// Masuk: 10% dari bawah layar. Keluar: tepi layar sebenarnya, agar elemen yang masih terlihat tidak ikut di-reset.
const enterObserver = supported
    ? new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) entry.target._motion?.enter();
        }
    }, { threshold: 0, rootMargin: '0px 0px -10% 0px' })
    : null;
const exitObserver = supported
    ? new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (!entry.isIntersecting && entry.boundingClientRect.top >= (entry.rootBounds?.bottom ?? window.innerHeight)) {
                entry.target._motion?.leaveBelow();
            }
        }
    }, { threshold: 0 })
    : null;

function watch(el, handlers) {
    el._motion = handlers;
    enterObserver.observe(el);
    exitObserver.observe(el);
}

function reveal(el, { expression, modifiers }) {
    if (reduce.matches || !supported) {
        return;
    }

    const variant = (expression || 'fade-up').trim();
    const stagger = modifiers.includes('stagger');
    const step = Number(el.dataset.revealDelay ?? (stagger ? staggerStep() : 0));
    const targets = stagger ? [...el.children] : [el];

    targets.forEach((target, i) => {
        target.dataset.reveal = variant;
        target.style.setProperty('--reveal-delay', `${stagger ? Math.min(i, STAGGER_MAX - 1) * step : step}ms`);
        // will-change hanya selama animasi.
        target.addEventListener('transitionend', (event) => {
            if (event.target === target) delete target.dataset.revealAnimating;
        });
    });

    const set = (state) => targets.forEach((target) => {
        if (target.dataset.revealState !== state) {
            target.dataset.revealAnimating = '';
            target.dataset.revealState = state;
        }
    });

    targets.forEach((target) => (target.dataset.revealState = seenOnLoad(el) ? 'shown' : 'hidden'));
    watch(el, { enter: () => set('shown'), leaveBelow: () => set('hidden') });
}

function count(el, { modifiers }) {
    const finalText = el.textContent.trim();
    const target = Number.parseInt(finalText.replace(/\D/g, ''), 10);

    if (reduce.matches || !supported || !target) {
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

    const format = new Intl.NumberFormat('id-ID');
    const delay = Number(modifiers.find((m) => !Number.isNaN(Number(m))) ?? 0);
    let timer = null;
    let frameId = null;
    const stop = () => {
        clearTimeout(timer);
        cancelAnimationFrame(frameId);
    };
    const run = () => {
        stop();
        el.textContent = format.format(0);
        timer = setTimeout(() => {
            const start = performance.now();
            const frame = (now) => {
                const t = Math.min(1, (now - start) / 800);
                el.textContent = t < 1 ? format.format(Math.round(target * (1 - (1 - t) ** 3))) : finalText;
                if (t < 1) frameId = requestAnimationFrame(frame);
            };
            frameId = requestAnimationFrame(frame);
        }, delay);
    };

    if (!seenOnLoad(el)) {
        el.textContent = format.format(0);
    }
    watch(el, {
        enter: run,
        leaveBelow: () => {
            stop();
            el.textContent = format.format(0);
        },
    });
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
