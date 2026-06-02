//


/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

// import './echo';
import QRCode from 'qrcode';

window.SantapQR = {
    async toCanvas(canvasEl, url, opts = {}) {
        await QRCode.toCanvas(canvasEl, url, {
            width: opts.width ?? 240,
            errorCorrectionLevel: 'H',
            margin: 2,
            color: { dark: '#000000', light: '#ffffff' },
            ...opts,
        });
    },
    async toPngDataUrl(url) {
        return await QRCode.toDataURL(url, {
            width: 240,
            errorCorrectionLevel: 'H',
            margin: 2,
        });
    },
};
