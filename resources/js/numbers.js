/**
 * Membaca angka yang diketik dalam format Indonesia. Logikanya sama dengan App\Support\NumberInput
 * (PHP); ubah keduanya bersamaan. Hasil: null (kosong), angka, atau NaN (tidak terbaca).
 *
 * kind: 'money' dan 'integer' membuang "Rp", spasi, titik, dan koma. 'decimal': koma = desimal,
 * titik = pemisah ribuan hanya bila setiap kelompok berisi tepat 3 digit ("1.250"), selain itu desimal.
 */
export function parseNumber(raw, kind) {
    const value = String(raw ?? '').trim();

    if (kind !== 'decimal') {
        const clean = value.replace(/^rp\.?/i, '').replace(/[\s.,]+/g, '');
        if (clean === '') {
            return null;
        }

        return /^-?\d{1,15}$/.test(clean) ? Number(clean) : NaN;
    }

    const clean = value.replace(/\s+/g, '');
    if (clean === '') {
        return null;
    }

    if (clean.includes(',')) {
        const match = clean.match(/^(-?)([1-9]\d{0,2}(?:\.\d{3})+|\d+),(\d+)$/);

        return match ? Number(`${match[1]}${match[2].replaceAll('.', '')}.${match[3]}`) : NaN;
    }

    if (/^-?[1-9]\d{0,2}(?:\.\d{3})+$/.test(clean)) {
        return Number(clean.replaceAll('.', ''));
    }

    return /^-?\d+(\.\d+)?$/.test(clean) ? Number(clean) : NaN;
}

export function formatNumber(value, kind = 'integer') {
    if (value === null || Number.isNaN(value)) {
        return '';
    }

    const text = new Intl.NumberFormat('id-ID', { maximumFractionDigits: kind === 'decimal' ? 2 : 0 }).format(value);

    return kind === 'money' ? `Rp ${text}` : text;
}

/** Teks "Terbaca: ..." di bawah input angka; null bila input kosong. */
export function numberPreview(raw, kind, { signed = false } = {}) {
    const value = parseNumber(raw, kind);

    if (value === null) {
        return null;
    }

    if (Number.isNaN(value)) {
        return { text: kind === 'decimal' ? 'Tidak terbaca. Tulis misalnya 12,5 atau 1.250.' : 'Tidak terbaca. Tulis angka saja, misalnya 1.250.000.', invalid: true };
    }

    if (value < 0 && !signed) {
        return { text: 'Angka tidak boleh negatif.', invalid: true };
    }

    if (kind === 'decimal' && (String(value).split('.')[1] ?? '').length > 2) {
        return { text: 'Paling banyak 2 angka di belakang koma.', invalid: true };
    }

    return { text: `Terbaca: ${formatNumber(value, kind)}`, invalid: false };
}
