// Stripe Tap to Pay, only inside the native counter app (Capacitor, android/).
// The plugin chunk is loaded lazily, browsers never fetch it.
import { postJson } from '@/lib/http';

export function isNativeApp() {
    return Boolean(window.Capacitor?.isNativePlatform?.());
}

let pluginPromise = null;
let readyPromise = null;

function plugin() {
    pluginPromise ??= import('@capacitor-community/stripe-terminal');
    return pluginPromise;
}

// SDK events to the server log; Stripe's own screen only says "Fehler"
function report(event, data = null) {
    postJson(route('team.terminal.log'), { event, data }).catch(() => {});
}

const REPORTED = ['RequestDisplayMessage', 'RequestReaderInput', 'PaymentStatusChange', 'ConnectionStatusChange', 'ReaderEvent', 'Failed', 'Canceled', 'DisconnectedReader', 'UnexpectedReaderDisconnect', 'ConnectedReader'];

async function connect({ location, simulated }) {
    const { StripeTerminal, TerminalConnectTypes, TerminalEventsEnum } = await plugin();
    for (const name of REPORTED) {
        await StripeTerminal.addListener(TerminalEventsEnum[name], (data) => report(name, data)).catch(() => {});
    }
    report('connect', { location, simulated });

    const granted = await StripeTerminal.requestPermissions({ permissions: ['location', 'bluetooth'] }).catch(() => null);
    if (granted?.location && granted.location !== 'granted') {
        throw new TapError('Standort-Berechtigung fehlt. In den App-Einstellungen erlauben.');
    }

    // token via the web session (cookie + CSRF), the native side has no login
    await StripeTerminal.addListener(TerminalEventsEnum.RequestedConnectionToken, async () => {
        const { status, data } = await postJson(route('team.terminal.token'), {});
        if (status !== 200) throw new TapError('Kein Zugang zu Stripe. Neu anmelden.');
        await StripeTerminal.setConnectionToken({ token: data.secret });
    });
    // test keys: Stripe simulates the tap
    await StripeTerminal.initialize({ isTest: simulated });

    const { reader } = await StripeTerminal.getConnectedReader();
    report('reader', reader);
    if (reader) return;

    const { readers } = await StripeTerminal.discoverReaders({ type: TerminalConnectTypes.TapToPay, locationId: location });
    if (!readers?.length) throw new TapError('Dieses Handy unterstützt Tap to Pay nicht.');
    await StripeTerminal.connectReader({ reader: readers[0], locationId: location, autoReconnectOnUnexpectedDisconnect: true });
}

export class TapError extends Error {}

/** Connects once per page load; a failed attempt can be retried. */
export function prepare(config) {
    readyPromise ??= connect(config).catch((e) => {
        readyPromise = null;
        throw e;
    });
    return readyPromise;
}

/** Shows the native tap screen and charges the server-created payment. */
export async function collect(config, clientSecret) {
    await prepare(config);
    const { StripeTerminal } = await plugin();
    try {
        report('collect');
        report('collected', await StripeTerminal.collectPaymentMethod({ paymentIntent: clientSecret }));
        await StripeTerminal.confirmPaymentIntent();
        report('confirmed');
    } catch (e) {
        report('error', { message: e?.message, code: e?.code, data: e?.data });
        throw e;
    }
}

export async function cancelCollect() {
    const { StripeTerminal } = await plugin();
    await StripeTerminal.cancelCollectPaymentMethod().catch(() => {});
}

/** Plain German text for the counter, details stay in the console. */
export function describe(error, fallback = 'Zahlung hat nicht geklappt.') {
    if (error instanceof TapError) return error.message;
    const text = `${error?.code ?? ''} ${error?.message ?? ''}`.toLowerCase();
    console.warn('tap to pay', error);
    if (text.includes('cancel')) return 'Abgebrochen.';
    if (text.includes('decline') || text.includes('card_declined')) return 'Karte abgelehnt.';
    if (text.includes('nfc')) return 'NFC ist aus. In den Einstellungen einschalten.';
    if (text.includes('insecure') || text.includes('developer')) return 'Entwickleroptionen am Handy ausschalten.';
    if (text.includes('attestation') || text.includes('unsupported')) return 'Dieses Handy unterstützt Tap to Pay nicht.';
    return fallback;
}
