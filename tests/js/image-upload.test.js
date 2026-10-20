// Jalankan: sail npm run test:js (node --test bawaan Node, tanpa paket tambahan).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ACCEPT_BASIC, ACCEPT_WITH_HEIC, imageAccept, isAppleTouchDevice, isHeicFile, isImageFile, renamed } from '../../resources/js/image-upload.js';

const iphone = { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', maxTouchPoints: 5 };
const ipadOld = { userAgent: 'Mozilla/5.0 (iPad; CPU OS 15_0 like Mac OS X) AppleWebKit/605.1.15 Version/15.0 Mobile/15E148 Safari/604.1', maxTouchPoints: 5 };
// iPadOS 13+ mengaku Mac di Safari; bedanya hanya layar sentuh.
const ipadAsMac = { userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15', maxTouchPoints: 5 };
const macbook = { userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15', maxTouchPoints: 0 };
const windows = { userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0 Safari/537.36', maxTouchPoints: 0 };
const android = { userAgent: 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 Chrome/140.0 Mobile Safari/537.36', maxTouchPoints: 5 };

test('iPhone, iPad, dan iPad yang mengaku Mac: tanpa HEIC agar iOS mengubah foto ke JPG sendiri', () => {
    for (const device of [iphone, ipadOld, ipadAsMac]) {
        assert.equal(isAppleTouchDevice(device), true);
        assert.equal(imageAccept(device), ACCEPT_BASIC);
        assert.equal(imageAccept(device).includes('heic'), false);
    }
});

test('laptop/desktop (Mac tanpa layar sentuh, Windows) dan Android: HEIC boleh dipilih', () => {
    for (const device of [macbook, windows, android]) {
        assert.equal(isAppleTouchDevice(device), false);
        assert.equal(imageAccept(device), ACCEPT_WITH_HEIC);
    }
    assert.equal(ACCEPT_WITH_HEIC, 'image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif');
});

test('lampiran (document) menambah PDF di semua perangkat', () => {
    assert.equal(imageAccept(iphone, 'document'), `${ACCEPT_BASIC},application/pdf`);
    assert.equal(imageAccept(windows, 'document'), `${ACCEPT_WITH_HEIC},application/pdf`);
});

test('tanpa navigator (mis. server) dianggap bukan perangkat Apple sentuh', () => {
    assert.equal(isAppleTouchDevice(undefined), false);
});

test('deteksi HEIC dari ekstensi atau tipe, dan nama file hasil konversi', () => {
    assert.equal(isHeicFile({ name: 'IMG_0001.HEIC', type: '' }), true);
    assert.equal(isHeicFile({ name: 'foto.heif', type: '' }), true);
    assert.equal(isHeicFile({ name: 'foto', type: 'image/heic' }), true);
    assert.equal(isHeicFile({ name: 'foto.jpg', type: 'image/jpeg' }), false);
    assert.equal(isImageFile({ name: 'foto.webp', type: 'image/webp' }), true);
    assert.equal(isImageFile({ name: 'surat.pdf', type: 'application/pdf' }), false);
    assert.equal(renamed('IMG_0001.HEIC', 'jpg'), 'IMG_0001.jpg');
    assert.equal(renamed('', 'jpg'), 'foto.jpg');
});
