/**
 * Grafik halaman Statistik dan dasbor Kelompok. Modul ini dimuat lewat import() dinamis dari app.js,
 * sehingga Chart.js hanya diunduh di halaman yang punya grafik. Hanya komponen yang dipakai yang didaftarkan.
 *
 * Gaya mengikuti token desain (CLAUDE.md Bagian 7): warna dari variabel CSS @theme, garis grid `line`,
 * Public Sans, tanpa gradasi, bayangan, atau animasi. Wadah canvas berukuran tetap (responsive +
 * maintainAspectRatio false) agar grafik mengikuti lebar kartu dan tidak pernah melebarkan halaman.
 */
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(ArcElement, BarController, BarElement, CategoryScale, DoughnutController, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip);

// Dibaca dari body agar nilai tema area aplikasi (.theme-app) ikut terpakai.
const token = (name, fallback) => getComputedStyle(document.body).getPropertyValue(`--color-${name}`).trim() || fallback;

/**
 * Warna segmen kepatuhan pelaporan, khusus grafik ini (CLAUDE.md Bagian 7). Semua ≥ 3:1 terhadap putih:
 * menunggu 3,7:1, perlu revisi 5,2:1, terlambat 8,9:1, belum 3,9:1. Terverifikasi memakai `brand`.
 */
const COMPLIANCE = {
    waiting: '#b07a00',
    revision: '#c2410c',
    overdue: '#8e1f17',
    pending: '#7d827f',
};

function palette() {
    return {
        ink: token('ink', '#1c1f1e'),
        inkSoft: token('ink-soft', '#4a504d'),
        // Label sumbu 11px: ink-3 (5,0:1 di atas putih).
        inkMuted: token('ink-3', '#636d67'),
        line: token('line', '#e3e5e1'),
        lineStrong: token('line-strong', '#c9ccc6'),
        paper: token('paper', '#ffffff'),
        brand: token('brand', '#1f5a47'),
    };
}

/**
 * Palet grafik area aplikasi (token --color-chart-*, urutan seri); warna terang hanya untuk isian, tidak pernah teks.
 * Nilai di sini cadangan bila variabel CSS-nya tidak ikut dibangun.
 */
const SERIES = {
    'chart-1': '#1f5a47',
    'chart-2': '#3e8e6e',
    'chart-3': '#8cc5a8',
    'chart-4': '#e9b949',
    'chart-5': '#d8bf98',
    'chart-other': '#c9cec9',
};

function toneColor(tone, colors) {
    if (tone in SERIES) {
        return token(tone, SERIES[tone]);
    }

    return {
        brand: colors.brand,
        ink: colors.inkSoft,
        verified: colors.brand,
        ...COMPLIANCE,
    }[tone] ?? colors.brand;
}

/** Donat (kartu Kelompok dasbor): warna per irisan dari palet grafik, "Lainnya" abu-abu. Legenda dan total di HTML. */
function renderDoughnut(canvas, config, colors) {
    const font = { family: getComputedStyle(document.body).fontFamily, size: 12 };

    return new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: config.labels,
            datasets: [{
                data: config.data,
                backgroundColor: config.tones.map((tone) => toneColor(tone, colors)),
                borderColor: colors.paper,
                borderWidth: 2,
                hoverOffset: 0,
            }],
        },
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: colors.ink,
                    titleColor: colors.paper,
                    bodyColor: colors.paper,
                    bodyFont: font,
                    padding: 8,
                    cornerRadius: 6,
                    callbacks: { label: (item) => `${item.label}: ${formatValue(item.raw, 'number')} kelompok` },
                },
            },
        },
    });
}

const numberFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const compactFormat = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });

function formatValue(value, format) {
    if (value === null || value === undefined) {
        return '—';
    }

    return format === 'rupiah' ? `Rp ${numberFormat.format(value)}` : numberFormat.format(value);
}

function formatTick(value, format) {
    return format === 'rupiah' ? `Rp ${compactFormat.format(value)}` : compactFormat.format(value);
}

/** Label kategori panjang dipotong di layar sempit agar sumbu tidak bertumpuk. */
function shortLabel(label, width) {
    const max = width < 420 ? 16 : 28;

    return label.length > max ? `${label.slice(0, max - 1)}…` : label;
}

function lineDataset(dataset, color, colors) {
    const provisional = dataset.provisional ?? [];

    return {
        borderWidth: 2,
        tension: 0,
        spanGaps: false,
        pointRadius: 3,
        pointHoverRadius: 5,
        pointBorderWidth: 2,
        pointBorderColor: color,
        // Bulan sementara: titik berlubang, dan segmen menuju titik itu putus-putus.
        pointBackgroundColor: dataset.data.map((_, index) => (provisional[index] ? colors.paper : color)),
        segment: { borderDash: (context) => (provisional[context.p1DataIndex] ? [6, 4] : undefined) },
    };
}

export function renderChart(canvas, config) {
    const colors = palette();
    if (config.type === 'doughnut') {
        return renderDoughnut(canvas, config, colors);
    }
    const horizontal = config.type === 'bar-horizontal';
    const stacked = config.type === 'bar-stacked';
    const line = config.type === 'line';
    const narrow = () => canvas.parentElement.clientWidth < 480;
    const font = { family: getComputedStyle(document.body).fontFamily, size: 13 };
    // Label sumbu 11px ink-3; grid putus-putus tipis.
    const axisFont = { ...font, size: 11 };

    const datasets = config.datasets.map((dataset) => {
        const color = toneColor(dataset.tone, colors);

        return {
            label: dataset.label,
            data: dataset.data,
            notes: dataset.notes ?? [],
            backgroundColor: color,
            borderColor: stacked ? colors.paper : color,
            ...(line
                ? lineDataset(dataset, color, colors)
                : stacked
                  ? { borderWidth: { top: 1 }, borderSkipped: 'start', maxBarThickness: 40 }
                  : { borderWidth: 0, borderRadius: 2, maxBarThickness: horizontal ? 24 : 40 }),
        };
    });

    const valueAxis = {
        beginAtZero: true,
        stacked,
        grid: { color: colors.line, drawTicks: false },
        border: { display: false, dash: [3, 3] },
        title: config.yTitle ? { display: true, text: config.yTitle, color: colors.inkMuted, font: axisFont } : { display: false },
        ticks: {
            color: colors.inkMuted,
            font: axisFont,
            padding: 8,
            maxRotation: 0,
            maxTicksLimit: horizontal ? (narrow() ? 3 : 5) : 6,
            callback: (value) => formatTick(value, config.format),
        },
    };
    const categoryAxis = {
        stacked,
        grid: { display: false },
        border: { color: colors.line },
        ticks: {
            color: colors.inkMuted,
            font: axisFont,
            maxRotation: 0,
            autoSkip: !horizontal,
            autoSkipPadding: 12,
            callback(value) {
                return shortLabel(this.getLabelForValue(value), canvas.parentElement.clientWidth);
            },
        },
    };

    const legend = config.legend ?? {};
    const lastIndex = config.labels.length - 1;

    return new Chart(canvas, {
        type: line ? 'line' : 'bar',
        data: { labels: config.labels, datasets },
        options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            interaction: { mode: 'index', intersect: false },
            scales: horizontal ? { x: valueAxis, y: categoryAxis } : { x: categoryAxis, y: valueAxis },
            plugins: {
                legend: {
                    display: legend.always || datasets.length > 1,
                    position: legend.position ?? 'bottom',
                    align: 'start',
                    title: legend.title ? { display: true, position: 'start', text: legend.title, color: colors.ink, font: { ...font, weight: 600 }, padding: { bottom: 4 } } : { display: false },
                    labels: {
                        color: colors.ink,
                        font: { ...font, size: 12 },
                        boxWidth: 10,
                        boxHeight: 10,
                        padding: 12,
                        usePointStyle: line,
                        pointStyle: 'circle',
                        // Legenda kepatuhan: jumlah bulan terakhir di setiap label, mis. "Terlambat (3)".
                        generateLabels(chart) {
                            const items = Chart.defaults.plugins.legend.labels.generateLabels(chart);

                            return legend.lastValue
                                ? items.map((item) => ({ ...item, text: `${item.text} (${formatValue(chart.data.datasets[item.datasetIndex].data[lastIndex], 'number')})` }))
                                : items;
                        },
                    },
                },
                tooltip: {
                    backgroundColor: colors.ink,
                    titleColor: colors.paper,
                    bodyColor: colors.paper,
                    footerColor: colors.paper,
                    titleFont: { ...font, weight: 600 },
                    bodyFont: font,
                    footerFont: { ...font, weight: 400 },
                    padding: 10,
                    cornerRadius: 6,
                    boxPadding: 4,
                    callbacks: {
                        label: (item) => `${item.dataset.label}: ${formatValue(item.raw, config.format)}`,
                        footer: (items) => [...new Set(items.flatMap((item) => item.dataset.notes[item.dataIndex] ?? []))],
                    },
                },
            },
        },
    });
}
