/**
 * Persiapan foto sebelum diunggah (x-file-upload dan x-admin.dropzone): pilihan accept per perangkat, konversi foto
 * iPhone (HEIC/HEIF) ke JPG dengan heic-to (dimuat hanya saat ada file HEIC), lalu diperkecil lewat canvas: sisi
 * terpanjang maks MAX_SIDE, arah mengikuti EXIF, metadata (termasuk GPS) terbuang. Server tetap memproses ulang.
 * Fungsi di sini tidak menyentuh window/document saat dimuat, sehingga bisa dites dengan node --test (tests/js).
 */

export const ACCEPT_BASIC = 'image/jpeg,image/png,image/webp';
export const ACCEPT_WITH_HEIC = `${ACCEPT_BASIC},image/heic,image/heif,.heic,.heif`;
export const MAX_SIDE = 2000;
export const JPEG_QUALITY = 0.82;
export const PREPARE_ERROR = 'Foto ini tidak bisa diproses. Coba kirim ulang dari iPhone dengan format Paling Kompatibel (Pengaturan > Kamera > Format), atau ubah ke JPG dulu.';

/** iPhone, iPod, iPad, termasuk iPadOS yang mengaku Mac (Macintosh + layar sentuh). */
export function isAppleTouchDevice(nav) {
    const userAgent = nav?.userAgent ?? '';

    return /iPhone|iPad|iPod/.test(userAgent) || (/Macintosh/.test(userAgent) && (nav?.maxTouchPoints ?? 0) > 1);
}

/**
 * iPhone/iPad: tanpa HEIC agar iOS mengubah foto ke JPG sendiri. Perangkat lain: HEIC boleh dipilih (file kiriman AirDrop,
 * WhatsApp, Drive). kind "document" menambah PDF (lampiran).
 */
export function imageAccept(nav, kind = 'photo') {
    const accept = isAppleTouchDevice(nav) ? ACCEPT_BASIC : ACCEPT_WITH_HEIC;

    return kind === 'document' ? `${accept},application/pdf` : accept;
}

/** Pasang accept sesuai perangkat pada <input type="file" data-image-accept="photo|document">. */
export function applyImageAccept(input, nav = globalThis.navigator) {
    if (input?.dataset?.imageAccept) {
        input.accept = imageAccept(nav, input.dataset.imageAccept);
    }
}

export function isHeicFile(file) {
    return /\.hei[cf]$/i.test(file?.name ?? '') || /^image\/hei[cf]/i.test(file?.type ?? '');
}

export function isImageFile(file) {
    return isHeicFile(file) || /^image\/(jpeg|png|webp)$/.test(file?.type ?? '');
}

/** "IMG_0001.HEIC" → "IMG_0001.jpg". */
export function renamed(name, extension) {
    const base = (name || 'foto').replace(/\.[^.]+$/, '') || 'foto';

    return `${base}.${extension}`;
}

/**
 * HEIC → JPG, lalu semua gambar digambar ulang di canvas (maks MAX_SIDE, kualitas JPEG_QUALITY). PNG tetap PNG agar
 * latar transparan tidak menjadi hitam. Gagal → Error (pemanggil menampilkan PREPARE_ERROR).
 */
export async function prepareImage(file) {
    let source = file;

    if (isHeicFile(file)) {
        const { heicTo } = await import('heic-to');
        source = await heicTo({ blob: file, type: 'image/jpeg', quality: JPEG_QUALITY });
    }

    if (!globalThis.createImageBitmap) {
        if (source === file) {
            return file;
        }

        return new File([source], renamed(file.name, 'jpg'), { type: 'image/jpeg' });
    }

    const bitmap = await createImageBitmap(source, { imageOrientation: 'from-image' });
    const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();

    const png = file.type === 'image/png';
    const type = png ? 'image/png' : 'image/jpeg';
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, png ? undefined : JPEG_QUALITY));

    if (!blob) {
        throw new Error('canvas');
    }

    return new File([blob], renamed(file.name, png ? 'png' : 'jpg'), { type });
}
