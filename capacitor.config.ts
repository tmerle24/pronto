import type { CapacitorConfig } from '@capacitor/cli';

// Native shell for the counter phone: loads the live site (remote mode) and adds
// Stripe Tap to Pay. Web changes deploy as usual; a new APK is only needed for
// native changes (plugins, icon, permissions).
const config: CapacitorConfig = {
    appId: 'de.tiamopizza.pronto',
    appName: 'TIAMO Pronto',
    // required even in remote mode; only offline.html is used from it
    webDir: 'android-web',
    server: {
        url: 'https://pronto.tiamo-pizza.de/team/counter',
        // whole host stays in the WebView (login redirect, queue, Inertia full reloads after a deploy)
        allowNavigation: ['pronto.tiamo-pizza.de'],
        cleartext: false,
        androidScheme: 'https',
        // bundled page when the site is unreachable at all
        errorPath: 'offline.html',
    },
    android: {
        backgroundColor: '#FBF4E6',
        allowMixedContent: false,
    },
};

export default config;
