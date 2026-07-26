const BARCODE_FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e'];

const QUAGGA_READERS = ['ean_reader', 'ean_8_reader', 'upc_reader', 'upc_e_reader'];

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

            (this.usingFallback ? this.startFallback() : this.startNative()).catch(() => {
                this.error = this.usingFallback
                    ? 'Unable to start the camera.'
                    : 'Camera access was denied.';
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
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
            });

            this.$refs.video.srcObject = this.stream;
            await this.$refs.video.play();

            this.detector = new BarcodeDetector({ formats: BARCODE_FORMATS });

            this.tick();
        },

        async tick() {
            if (! this.stream) {
                return;
            }

            try {
                const codes = await this.detector.detect(this.$refs.video);

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
                    target: this.$refs.quaggaTarget,
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
