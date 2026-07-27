import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Audio notification chime using Web Audio API
function playAdminAudioChime() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        const ctx = new AudioContextClass();
        if (ctx.state === 'suspended') {
            ctx.resume();
        }

        const now = ctx.currentTime;
        const playNote = (freq, start, duration) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, start);
            gain.gain.setValueAtTime(0.15, start);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(start);
            osc.stop(start + duration);
        };

        playNote(523.25, now, 0.2); // C5
        playNote(659.25, now + 0.1, 0.2); // E5
        playNote(783.99, now + 0.2, 0.35); // G5
    } catch (e) {
        console.warn('Could not play audio chime:', e);
    }
}

// Trigger Filament Notification Banner / Toast
function showAdminNotification({ title, body, color, icon }) {
    playAdminAudioChime();

    // Filament v3 notification dispatch via JS API
    if (typeof FilamentNotification !== 'undefined') {
        new FilamentNotification()
            .title(title)
            .body(body)
            .icon(icon || 'heroicon-o-shopping-bag')
            .iconColor(color || 'success')
            .send();
    } else {
        window.dispatchEvent(new CustomEvent('notify', {
            detail: { title, body, color, icon }
        }));
    }
}

// Subscribe to active user's organization channels in Filament Admin
const initAdminListeners = () => {
    if (window.SantapUser && Array.isArray(window.SantapUser.orgIds)) {
        window.SantapUser.orgIds.forEach((orgId) => {
            console.log(`Subscribing to real-time events on organization.${orgId}`);
            
            window.Echo.private(`organization.${orgId}`)
                .listen('.order-placed', (e) => {
                    console.log('Real-Time Order Placed Received:', e);
                    const orderNo = e.orderNumber || e.order_number || '';
                    const tableName = e.tableName || e.table_name || 'Meja';
                    const amount = Number(e.totalAmount || e.total_amount || 0).toLocaleString('id-ID');

                    showAdminNotification({
                        title: '🔔 Pesanan Baru Masuk!',
                        body: `Pesanan #${orderNo} (${tableName}) - Rp ${amount}`,
                        color: 'success',
                        icon: 'heroicon-o-shopping-bag',
                    });
                })
                .listen('.repeat-order-created', (e) => {
                    console.log('Real-Time Repeat Order Received:', e);
                    const orderNo = e.orderNumber || e.order_number || '';
                    const itemsCount = e.items?.length || e.batch?.items_count || 1;

                    showAdminNotification({
                        title: '🔔 Tambahan Menu Open Bill!',
                        body: `Open Bill #${orderNo} mendapat ${itemsCount} item baru.`,
                        color: 'info',
                        icon: 'heroicon-o-arrow-path',
                    });
                });
        });
    }
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminListeners);
} else {
    initAdminListeners();
}
