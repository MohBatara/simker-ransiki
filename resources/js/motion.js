/**
 * Efek scroll website publik (satu sistem). Semua mati bila prefers-reduced-motion atau tanpa IntersectionObserver;
 * konten selalu dirender tampil oleh server, kelas penyembunyi baru dipasang di sini. Jarak, skala, durasi, easing,
 * dan jeda stagger diatur di SATU tempat: variabel --reveal-* di resources/css/app.css.
 *
 * x-reveal="fade-up | fade-left | fade-right | zoom-in"      satu elemen (data-reveal-delay="ms" opsional)
 * x-reveal.stagger="fade-up"                                 anak elemen bergiliran (data-reveal-delay = jeda per item)
 *   + data-reveal-row                                        deretan geser: < 1024px, selama deretan bisa digeser, varian
 *                                                            "slide-in-row" (kartu masuk dari kanan) menggantikan varian tadi
 *   + .intro                                                 terlihat saat dibuka: tetap dimainkan setelah intro hero
 * x-reveal.quick / x-reveal.stagger.quick                     versi ringan (Direktori): SEKALI per elemen, tiap anak
 *                                                            diamati sendiri, pemicu --reveal-quick-threshold terlihat,
 *                                                            jarak/durasi/jeda --reveal-quick-* (atribut data-reveal-quick)
 * x-count.<tunda ms>                                         angka menghitung naik setiap kali masuk dari bawah
 * x-parallax="<px>"                                          geser vertikal halus selama elemen di layar (≥ 768px)
 * $store.scroll.pastHeader / pastFold                        posisi gulir (header ringkas, tombol kembali ke atas)
 *
 * Berulang: muncul saat masuk dari bawah, kembali tersembunyi hanya bila keluar lewat BAWAH (pengunjung menggulir naik
 * melewatinya), tetap tampil bila keluar lewat atas.
 */
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const desktop = window.matchMedia('(min-width: 48rem)');
const narrow = window.matchMedia('(max-width: 63.98rem)');
const INTRO_DELAY = 360; // sama dengan jeda .motion [data-intro-late] di app.css
const scrollsX = (el) => ['auto', 'scroll'].includes(getComputedStyle(el).overflowX);
const STAGGER_MAX = 6;
// Konstanta dibaca dari variabel --reveal-* / --count-* di app.css (satu tempat untuk menyetel).
const token = (name, fallback) => Number.parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name)) || fallback;
const staggerStep = () => token('--reveal-stagger', 150);

// Elemen yang sudah terlihat (atau sudah lewat di atas layar) saat dibuka tidak disembunyikan.
const seenOnLoad = (el) => el.getBoundingClientRect().top < window.innerHeight;

const supported = 'IntersectionObserver' in window;
// Masuk: setelah elemen melewati --reveal-trigger dari bawah layar dan minimal --reveal-threshold-nya terlihat. Elemen
// yang lebih tinggi dari area pemicu tidak pernah mencapai rasio itu, jadi diukur terhadap tinggi area pemicu.
// Keluar: tepi layar sebenarnya (observer kedua), agar elemen tidak berkedip di batas pemicu.
const enterThreshold = supported ? token('--reveal-threshold', 0.15) : 0;
const enterObserver = supported
    ? new IntersectionObserver((entries) => {
        for (const entry of entries) {
            const needed = enterThreshold * Math.min(entry.boundingClientRect.height, entry.rootBounds?.height ?? Infinity);
            if (entry.isIntersecting && entry.intersectionRect.height >= needed - 1) entry.target._motion?.enter();
        }
    }, {
        threshold: [0, enterThreshold / 3, (enterThreshold * 2) / 3, enterThreshold],
        rootMargin: `0px 0px -${token('--reveal-trigger', 25)}% 0px`,
    })
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

// Versi ringan: elemen muncul sekali saat --reveal-quick-threshold bagiannya terlihat, lalu tidak diamati lagi.
const quickThreshold = supported ? token('--reveal-quick-threshold', 0.1) : 0;
const quickObserver = supported
    ? new IntersectionObserver((entries) => {
        const entering = entries.filter((entry) => entry.isIntersecting);
        // Elemen yang masuk bersamaan dijeda bergiliran (maks STAGGER_MAX).
        entering.forEach((entry, i) => {
            quickObserver.unobserve(entry.target);
            entry.target._motion?.enter(Math.min(i, STAGGER_MAX - 1));
        });
    }, { threshold: quickThreshold })
    : null;

function quick(el, { modifiers }) {
    const targets = modifiers.includes('stagger') ? [...el.children] : [el];
    const step = Number(el.dataset.revealDelay ?? token('--reveal-quick-stagger', 60));
    targets.forEach((target) => {
        target.dataset.reveal = 'fade-up';
        target.dataset.revealQuick = '';
        target.addEventListener('transitionend', (event) => {
            if (event.target === target) delete target.dataset.revealAnimating;
        });
        if (seenOnLoad(target)) {
            target.dataset.revealState = 'shown';
            return;
        }
        target.dataset.revealState = 'hidden';
        target._motion = {
            enter: (index) => {
                target.style.setProperty('--reveal-delay', `${index * step}ms`);
                target.dataset.revealAnimating = '';
                target.dataset.revealState = 'shown';
            },
        };
        quickObserver.observe(target);
    });
}

function watch(el, handlers) {
    el._motion = handlers;
    enterObserver.observe(el);
    exitObserver.observe(el);
}

function reveal(el, { expression, modifiers }) {
    if (reduce.matches || !supported) {
        return;
    }
    if (modifiers.includes('quick')) {
        quick(el, { modifiers });
        return;
    }

    const variant = (expression || 'fade-up').trim();
    const stagger = modifiers.includes('stagger');
    const step = Number(el.dataset.revealDelay ?? (stagger ? staggerStep() : 0));
    const targets = stagger ? [...el.children] : [el];

    // Varian dipilih ulang saat lebar layar melewati batas (deretan geser menjadi grid atau sebaliknya).
    const apply = () => {
        const row = 'revealRow' in el.dataset && narrow.matches && scrollsX(el);
        const gap = row ? token('--reveal-row-stagger', 120) : step;
        targets.forEach((target, i) => {
            target.dataset.reveal = row ? 'slide-in-row' : variant;
            target.style.setProperty('--reveal-delay', `${stagger ? Math.min(i, STAGGER_MAX - 1) * gap : gap}ms`);
        });

        return row;
    };
    const row = apply();
    narrow.addEventListener('change', apply);
    desktop.addEventListener('change', apply);

    targets.forEach((target) => {
        // will-change hanya selama animasi.
        target.addEventListener('transitionend', (event) => {
            if (event.target === target) delete target.dataset.revealAnimating;
        });
    });

    // Browser mengikuti titik snap kartu yang sedang bergeser (deretan ikut menggulir sendiri), jadi snap deretan
    // dimatikan selama kartu tersembunyi atau masuk, lalu dipulihkan setelah kartu terakhir selesai.
    let snapTimer = null;
    const snap = (state) => {
        if (!('revealRow' in el.dataset)) return;
        clearTimeout(snapTimer);
        el.style.scrollSnapType = 'none';
        if (state === 'shown') {
            const last = Math.max(0, ...targets.map((target) => Number.parseFloat(target.style.getPropertyValue('--reveal-delay')) || 0));
            const duration = token(targets[0].dataset.reveal === 'slide-in-row' ? '--reveal-row-duration' : '--reveal-duration', 1000);
            snapTimer = setTimeout(() => (el.style.scrollSnapType = ''), last + duration);
        }
    };

    const set = (state) => {
        if (targets.every((target) => target.dataset.revealState === state)) return;
        snap(state);
        targets.forEach((target) => {
            if (target.dataset.revealState !== state) {
                target.dataset.revealAnimating = '';
                target.dataset.revealState = state;
                target.dispatchEvent(new CustomEvent(`reveal:${state}`, { bubbles: true }));
            }
        });
    };

    let introPending = row && modifiers.includes('intro') && seenOnLoad(el);
    const initial = seenOnLoad(el) && !introPending ? 'shown' : 'hidden';
    targets.forEach((target) => (target.dataset.revealState = initial));
    if (initial === 'hidden') snap('hidden');
    if (introPending) {
        setTimeout(() => {
            introPending = false;
            set('shown');
        }, INTRO_DELAY);
    }
    watch(el, { enter: () => introPending || set('shown'), leaveBelow: () => set('hidden') });
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
    const duration = token('--count-duration', 1400);
    const loadDelay = Number(modifiers.find((m) => !Number.isNaN(Number(m))) ?? 0);
    let timer = null;
    let frameId = null;
    const stop = () => {
        clearTimeout(timer);
        cancelAnimationFrame(frameId);
    };
    const run = (delay) => {
        stop();
        el.textContent = format.format(0);
        timer = setTimeout(() => {
            const start = performance.now();
            const frame = (now) => {
                const t = Math.min(1, (now - start) / duration);
                el.textContent = t < 1 ? format.format(Math.round(target * (1 - (1 - t) ** 3))) : finalText;
                if (t < 1) frameId = requestAnimationFrame(frame);
            };
            frameId = requestAnimationFrame(frame);
        }, delay);
    };

    const reset = () => {
        stop();
        el.textContent = format.format(0);
    };

    // Di dalam elemen ber-x-reveal: mulai bersamaan dengan munculnya elemen itu (ikut jeda stagger-nya).
    const host = el.closest('[data-reveal]');
    if (host) {
        host.addEventListener('reveal:shown', (event) => event.target === host && run(Number.parseFloat(host.style.getPropertyValue('--reveal-delay')) || 0));
        host.addEventListener('reveal:hidden', (event) => event.target === host && reset());
        if (host.dataset.revealState === 'shown') {
            run(loadDelay);
        } else {
            el.textContent = format.format(0);
        }
        return;
    }

    if (!seenOnLoad(el)) {
        el.textContent = format.format(0);
    }
    watch(el, { enter: () => run(loadDelay), leaveBelow: reset });
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
