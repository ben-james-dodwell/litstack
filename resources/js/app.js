import barcodeScanner from './barcode-scanner';

// Exposed globally so it can also be spread into an existing x-data object
// (e.g. `x-data="{ foo: false, ...barcodeScanner() }"`), not just used bare.
window.barcodeScanner = barcodeScanner;

document.addEventListener('alpine:init', () => {
    Alpine.data('barcodeScanner', barcodeScanner);
});
