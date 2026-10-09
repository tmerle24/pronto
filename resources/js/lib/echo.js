import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { ref } from 'vue';

let echo = null;
export const connected = ref(false);

// Created on demand: only queue, display and status page need a socket.
export function useEcho(config) {
    if (!config) return null;
    if (echo) return echo;

    window.Pusher = Pusher;
    echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    echo.connector.pusher.connection.bind('state_change', ({ current }) => {
        connected.value = current === 'connected';
    });

    return echo;
}
