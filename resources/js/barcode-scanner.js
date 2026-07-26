const BARCODE_FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e'];

const QUAGGA_READERS = ['ean_reader', 'ean_8_reader', 'upc_reader', 'upc_e_reader'];

// Plain DOM ids rather than x-ref: Flux's <flux:modal> renders its slot
// inside a <dialog x-data="fluxModal(...)">, a nested Alpine component whose
// boundary $refs cannot cross, so this.$refs.video would never resolve.
const VIDEO_ELEMENT_ID = 'barcode-scanner-video';
const QUAGGA_TARGET_ELEMENT_ID = 'barcode-scanner-quagga-target';

export default function barcodeScanner(method = 'scanIsbn') {
    return {
        method,
        error: null,
        usingFallback: false,
        stream: null,
        detector: null,
        rafId: null,
        quagga: null,

        start() {
            this.error = null;
            this.usingFallback = ! ('BarcodeDetector' in window);

            (this.usingFallback ? this.startFallback() : this.startNative()).catch((e) => {
                console.error('Barcode scanner failed to start', e);

                this.error = e?.name
                    ? `Unable to start the camera (${e.name}): ${e.message}`
                    : 'Unable to start the camera.';
            });
        },

        stop() {
            if (this.rafId) {
                cancelAnimationFrame(this.rafId);
                this.rafId = null;
            }

            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;
            }

            if (this.quagga) {
                this.quagga.offDetected();
                this.quagga.stop();
                this.quagga = null;
            }
        },

        async startNative() {
            if (! navigator.mediaDevices?.getUserMedia) {
                throw new DOMException(
                    'Camera access requires HTTPS (or localhost).',
                    'SecurityError',
                );
            }

            const video = document.getElementById(VIDEO_ELEMENT_ID);

            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
            });

            video.srcObject = this.stream;
            await video.play();

            this.detector = new BarcodeDetector({ formats: BARCODE_FORMATS });

            this.tick();
        },

        async tick() {
            if (! this.stream) {
                return;
            }

            try {
                const codes = await this.detector.detect(document.getElementById(VIDEO_ELEMENT_ID));

                if (codes.length > 0) {
                    this.handleDetected(codes[0].rawValue);

                    return;
                }
            } catch {
                // Ignore transient decode errors and keep scanning.
            }

            this.rafId = requestAnimationFrame(() => this.tick());
        },

        async startFallback() {
            const { default: Quagga } = await import('@ericblade/quagga2');

            this.quagga = Quagga;

            await Quagga.start({
                inputStream: {
                    type: 'LiveStream',
                    target: document.getElementById(QUAGGA_TARGET_ELEMENT_ID),
                    constraints: { facingMode: 'environment' },
                },
                decoder: { readers: QUAGGA_READERS },
                locate: true,
            });

            Quagga.onDetected((result) => this.handleDetected(result.codeResult.code));
        },

        handleDetected(code) {
            this.stop();
            this.$wire.call(this.method, code);
            this.$flux.modal('barcode-scanner').close();
        },
    };
}
