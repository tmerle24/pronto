#!/usr/bin/env python3
"""
stripe-setup.py — Stripe für TIAMO Pronto einrichten (Test- oder Live-Modus).

    cd /var/www/pronto && python3 server/stripe-setup.py

Fragt Publishable und Secret Key verdeckt ab (nichts landet im Terminal-Verlauf
oder in der Prozessliste) und erledigt dann über die Stripe-API:

  1. Account prüfen (zeigt Name und Land, damit es der richtige ist)
  2. Webhook auf https://<domain>/webhooks/stripe anlegen
     (payment_intent.succeeded, payment_intent.payment_failed), Secret speichern
  3. Domain für Apple Pay / Google Pay registrieren
  4. Zahlungsarten prüfen, PayPal einschalten, falls verfügbar
  5. Terminal-Standort für Tap to Pay anlegen oder wiederverwenden
  6. STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET, STRIPE_TERMINAL_LOCATION
     in .env, Caches und Dienste neu laden

Erneut ausführbar, z. B. beim Wechsel von Test- auf Live-Keys. API-Aufrufe
kosten bei Stripe nichts.
"""

import getpass
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request

APP_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ENV_PATH = os.path.join(APP_DIR, '.env')
EVENTS = ['payment_intent.succeeded', 'payment_intent.payment_failed']


def ok(msg): print(f'  \033[32m✓\033[0m {msg}')
def warn(msg): print(f'  \033[33m!\033[0m {msg}')
def die(msg): print(f'\n\033[31m✗ {msg}\033[0m', file=sys.stderr); sys.exit(1)


def env_get(key):
    for line in open(ENV_PATH).read().splitlines():
        m = re.match(rf'^{re.escape(key)}=(.*)$', line)
        if m:
            return m.group(1).strip().strip('"')
    return ''


def env_set(values):
    lines = open(ENV_PATH).read().splitlines()
    for key, value in values.items():
        rendered = f'{key}={value}'
        for i, line in enumerate(lines):
            if re.match(rf'^\s*#?\s*{re.escape(key)}=', line):
                lines[i] = rendered
                break
        else:
            lines.append(rendered)
    with open(ENV_PATH, 'w') as f:
        f.write('\n'.join(lines) + '\n')


class Stripe:
    def __init__(self, secret):
        self.secret = secret

    def call(self, method, path, params=None):
        data = None
        url = 'https://api.stripe.com/v1' + path
        if params and method == 'GET':
            url += '?' + urllib.parse.urlencode(params, doseq=True)
        elif params:
            data = urllib.parse.urlencode(params, doseq=True).encode()
        request = urllib.request.Request(url, data=data, method=method, headers={
            'Authorization': 'Bearer ' + self.secret,
            'Stripe-Version': '2024-06-20',
        })
        try:
            with urllib.request.urlopen(request, timeout=20) as response:
                return json.loads(response.read())
        except urllib.error.HTTPError as e:
            body = json.loads(e.read() or b'{}')
            raise RuntimeError(body.get('error', {}).get('message', f'HTTP {e.code}'))


def main():
    if not os.path.exists(ENV_PATH):
        die(f'{ENV_PATH} fehlt. Erst install.sh ausführen.')

    app_url = env_get('APP_URL').rstrip('/')
    domain = urllib.parse.urlparse(app_url).hostname
    if not app_url.startswith('https://') or not domain:
        die(f'APP_URL muss https sein (ist: {app_url!r}).')

    print('\n  TI♥AMO Pronto — Stripe einrichten\n')
    print(f'  Domain: {domain}')
    print('  Keys findest du im Stripe-Dashboard unter Entwickler → API-Schlüssel.')
    print('  Für den Probelauf: Testmodus (pk_test_/sk_test_), für den Markt: Live.\n')

    publishable = getpass.getpass('  Publishable Key (pk_…, Eingabe unsichtbar): ').strip()
    secret = getpass.getpass('  Secret Key (sk_… oder rk_…, Eingabe unsichtbar): ').strip()

    mode = 'test' if publishable.startswith('pk_test_') else 'live' if publishable.startswith('pk_live_') else None
    if mode is None:
        die('Publishable Key muss mit pk_test_ oder pk_live_ beginnen.')
    if not re.match(rf'^(sk|rk)_{mode}_', secret):
        die(f'Secret Key passt nicht zum {mode}-Modus des Publishable Keys.')

    stripe = Stripe(secret)

    # 1. account
    try:
        account = stripe.call('GET', '/account')
    except RuntimeError as e:
        die(f'Stripe lehnt den Key ab: {e}')
    name = (account.get('settings', {}).get('dashboard', {}) or {}).get('display_name') or account.get('business_profile', {}).get('name') or account.get('id')
    ok(f'Account: {name} ({account.get("country")}), Modus: {mode.upper()}')
    if not account.get('charges_enabled') and mode == 'live':
        warn('Zahlungen sind für diesen Account noch nicht freigeschaltet (Onboarding im Dashboard abschließen).')
    if input('  Richtiger Account? [j/N]: ').strip().lower() not in ('j', 'y', 'ja', 'yes'):
        die('Abgebrochen, nichts geändert.')

    # 2. webhook: Stripe zeigt das Secret nur beim Anlegen, also eigenen alten Endpoint ersetzen
    url = f'{app_url}/webhooks/stripe'
    existing = [w for w in stripe.call('GET', '/webhook_endpoints', {'limit': 100})['data'] if w['url'] == url]
    for endpoint in existing:
        stripe.call('DELETE', f'/webhook_endpoints/{endpoint["id"]}')
        ok(f'alten Webhook {endpoint["id"]} ersetzt')
    webhook = stripe.call('POST', '/webhook_endpoints', {
        'url': url,
        'enabled_events[]': EVENTS,
        'description': 'TIAMO Pronto',
    })
    ok(f'Webhook {webhook["id"]} → {url}')

    # 3. domain for Apple Pay / Google Pay
    try:
        domains = stripe.call('GET', '/payment_method_domains', {'domain_name': domain})['data']
        pmd = domains[0] if domains else stripe.call('POST', '/payment_method_domains', {'domain_name': domain})
        if not pmd.get('enabled', True):
            pmd = stripe.call('POST', f'/payment_method_domains/{pmd["id"]}', {'enabled': 'true'})
        states = {k: (pmd.get(k) or {}).get('status') for k in ('apple_pay', 'google_pay', 'paypal', 'link')}
        ok('Domain registriert: ' + ', '.join(f'{k} {v}' for k, v in states.items() if v))
        if states.get('apple_pay') != 'active':
            try:
                stripe.call('POST', f'/payment_method_domains/{pmd["id"]}/validate')
                ok('Apple-Pay-Domain erneut geprüft')
            except RuntimeError as e:
                warn(f'Apple Pay noch nicht aktiv: {e}')
    except RuntimeError as e:
        warn(f'Domain-Registrierung: {e}')

    # 4. payment methods (default configuration)
    try:
        configs = stripe.call('GET', '/payment_method_configurations', {'limit': 20})['data']
        config = next((c for c in configs if c.get('is_default')), configs[0] if configs else None)
        if config:
            def state(key):
                pm = config.get(key) or {}
                return 'an' if pm.get('available') else ('nicht verfügbar' if not pm else 'aus')
            if not (config.get('paypal') or {}).get('available'):
                try:
                    config = stripe.call('POST', f'/payment_method_configurations/{config["id"]}', {'paypal[display_preference][preference]': 'on'})
                    ok('PayPal eingeschaltet')
                except RuntimeError as e:
                    warn(f'PayPal ließ sich nicht einschalten ({e}). Im Dashboard unter Einstellungen → Zahlungsmethoden aktivieren.')
            ok('Zahlungsarten: ' + ', '.join(f'{k} {state(k)}' for k in ('card', 'apple_pay', 'google_pay', 'paypal')))
    except RuntimeError as e:
        warn(f'Zahlungsarten nicht lesbar: {e}')

    # 5. terminal location for Tap to Pay (per mode; reused by metadata)
    location_id = ''
    try:
        locations = stripe.call('GET', '/terminal/locations', {'limit': 100})['data']
        location = next((l for l in locations if (l.get('metadata') or {}).get('pronto') == '1'), None)
        if location:
            ok(f'Terminal-Standort {location["id"]} ({location.get("display_name")}) wiederverwendet')
        else:
            print('  Tap to Pay braucht einen Standort (Adresse des Stands bzw. der Firma):')
            line1 = input('    Straße und Hausnummer: ').strip()
            postal = input('    PLZ: ').strip()
            city = input('    Ort: ').strip()
            if not (line1 and postal and city):
                raise RuntimeError('Adresse unvollständig, Standort nicht angelegt')
            location = stripe.call('POST', '/terminal/locations', {
                'display_name': 'TIAMO Pronto',
                'address[line1]': line1,
                'address[postal_code]': postal,
                'address[city]': city,
                'address[country]': 'DE',
                'metadata[pronto]': '1',
            })
            ok(f'Terminal-Standort {location["id"]} angelegt')
        location_id = location['id']
    except RuntimeError as e:
        warn(f'Tap to Pay nicht eingerichtet: {e}')

    # 6. .env and reload
    env_set({'STRIPE_KEY': publishable, 'STRIPE_SECRET': secret, 'STRIPE_WEBHOOK_SECRET': webhook['secret'],
             'STRIPE_TERMINAL_LOCATION': location_id})
    ok('.env aktualisiert (Keys werden nicht angezeigt)')

    for cmd in (['php', 'artisan', 'config:cache'],
                ['sudo', 'systemctl', 'reload', 'php8.4-fpm'],
                ['sudo', 'supervisorctl', 'restart', 'pronto-worker:*']):
        subprocess.run(cmd, cwd=APP_DIR, check=False, stdout=subprocess.DEVNULL)
    ok('Konfiguration neu geladen')

    print(f'\n✅ Stripe ({mode}) ist eingerichtet. Test: {app_url} öffnen, bestellen, bezahlen.')
    if mode == 'test':
        print('   Testkarte 4242 4242 4242 4242, beliebiges Ablaufdatum in der Zukunft, beliebige Prüfziffer.')
        print('   Für den Markt das Skript mit Live-Keys erneut ausführen.')


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        die('Abgebrochen.')
