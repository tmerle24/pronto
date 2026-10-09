# TIAMO Pronto

Bestell- und Abholsystem für den mobilen Pizza-Stand **TIAMO Pizza**. Gäste scannen einen QR-Code, bestellen und bezahlen am
eigenen Handy. Timo arbeitet die Bestellungen am Tablet ab, vorne zeigt ein zweites Tablet die Abholnummern, und das Handy des
Gasts meldet sich, wenn die Pizza fertig ist.

Live: <https://pronto.tiamo-pizza.de>

## Oberflächen

| Oberfläche | Gerät | Adresse | Zugang |
| --- | --- | --- | --- |
| Gästeseite (Karte, Bezahlen, Status) | Gast-Handy | `/`, `/e/{event}`, `/o/{token}` | öffentlich |
| Cucina (Queue) | Tablet hinter der Theke | `/team/queue` | Team-Login |
| Cassa (Theke) | Handy/Tablet | `/team/counter` | Team-Login |
| Cassa-App (Tap to Pay) | Android-Handy | App "TIAMO Pronto", Download unter `/team/app` | Team-Login |
| Tabellone (Abhol-Display) | Tablet vorne, quer | `/display/{token}` | geheimer Link (Admin, Events, "Display") |
| Admin | Laptop/Handy | `/team/admin` | Rolle Admin |

## Technik

Laravel 13, Inertia 3 + Vue 3, Tailwind 4, Filament 5 (Admin), Reverb (WebSockets), PostgreSQL 16. Queue und Cache über die
Datenbank. Stripe (Payment Element, Webhook), Web Push (VAPID), WhatsApp Cloud API / 360dialog, seven.io für SMS.

Wichtige Stellen im Code:

- `app/Enums/OrderStatus.php` - Status-Workflow `new → in_progress → ready → picked_up` (+ `cancelled`)
- `app/Services/Orders/` - Bestellung anlegen (`PlaceOrder`), Statuswechsel mit Undo und Idempotenz (`OrderWorkflow`),
  Abholnummern (`OrderQueue`), Zahlungen (`OrderPayments`), Storno (`CancelOrder`)
- `app/Services/WaitTime/` - Wartezeit (rein und unit-getestet: `WaitTimeEstimator`)
- `app/Services/Notifications/` - Web Push, WhatsApp, SMS hinter `CustomerNotifier`, Tageslimit für bezahlte Kanäle
- `app/Services/Fiscal/` - `FiscalRecorder` (Standard: `NullFiscalRecorder`, vorbereitet: fiskaly)
- `resources/js/Pages/` - Gäste-, Team- und Display-Seiten
- `config/brand.php`, `config/pronto.php` - Branding und alle Schalter

## Lokal starten

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
docker compose up -d                 # Postgres auf Port 5447
php artisan migrate --seed           # Karte, Event "Weihnachtsmarkt Stierstadt", Demo-Logins
npm run build                        # oder: npm run dev
php artisan serve --port=8047
php artisan reverb:start --port=8048 # Echtzeit (zweites Terminal)
php artisan queue:work               # Benachrichtigungen (drittes Terminal)
```

Demo-Logins (nur lokal, siehe `database/seeders/DemoSeeder.php`): `admin@pronto.test`, `cucina@pronto.test`.

- Ohne Stripe-Keys gibt es auf der Bezahlseite den Knopf "Testzahlung abschließen" (nur außerhalb von Produktion).
- Testbestellungen für Queue und Display: `php artisan pronto:demo-orders 5`
- Stripe lokal testen: `stripe listen --forward-to localhost:8047/webhooks/stripe`, das ausgegebene `whsec_...` als
  `STRIPE_WEBHOOK_SECRET` eintragen, dazu `STRIPE_KEY`/`STRIPE_SECRET` aus dem Testmodus.
- Lasttest (Bestellungen, Statusseiten per WebSocket, Queue-Taps): `node scripts/loadtest.mjs http://localhost:8047 60 50`

Prüfen:

```bash
php artisan test
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint
```

Tests rufen nie eine echte API: Der Stripe-Client wirft im Test eine Exception, HTTP-Aufrufe sind gesperrt
(`Http::preventStrayRequests()`), Web Push läuft gegen eine Attrappe.

## ENV-Variablen

| Variable | Wofür |
| --- | --- |
| `APP_URL` | `https://pronto.tiamo-pizza.de` |
| `DB_*` | PostgreSQL |
| `STRIPE_KEY`, `STRIPE_SECRET` | Stripe (Publishable/Secret Key). Leer = in Produktion nur Bar |
| `STRIPE_WEBHOOK_SECRET` | Signatur des Webhooks `/webhooks/stripe` |
| `STRIPE_TERMINAL_LOCATION` | Stripe-Terminal-Standort für Tap to Pay (`tml_...`, legt `stripe-setup.py` an). Leer = kein Tap to Pay |
| `REVERB_APP_ID/KEY/SECRET` | Reverb-Zugang (install.sh erzeugt sie) |
| `REVERB_HOST/PORT/SCHEME` | wie der Server Reverb erreicht (intern `127.0.0.1:8048`) |
| `REVERB_PUBLIC_HOST/PORT/SCHEME` | wie Browser Reverb erreichen (Domain, 443, https über nginx) |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` | Web Push, erzeugen mit `php artisan pronto:vapid` |
| `WHATSAPP_DRIVER` | `log` (Standard, sendet nichts), `meta` oder `360dialog` |
| `WHATSAPP_NUMBER` | unsere Nummer ohne +, für den `wa.me`-Link |
| `WHATSAPP_TOKEN`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_APP_SECRET`, `WHATSAPP_VERIFY_TOKEN` | Cloud API bzw. 360dialog-Key; Webhook `/webhooks/whatsapp` |
| `SMS_DRIVER`, `SMS_API_KEY`, `SMS_SENDER` | `log` (Standard) oder `seven` |
| `SMS_DAILY_LIMIT`, `WHATSAPP_DAILY_LIMIT` | harte Obergrenze pro Tag (Kosten), Standard 50 / 300 |
| `BUSINESS_NAME/ADDRESS/TAX_NUMBER/VAT_ID` | Angaben auf dem Beleg |
| `FISCAL_DRIVER`, `FISKALY_*` | TSE, Standard `null` (erst nach Rücksprache mit dem Steuerberater) |

Jede Nachricht an einen bezahlten Anbieter steht in der Tabelle `outbound_messages` (ohne Kontaktdaten).

## Produktion (VPS, ohne Docker)

Der Server (`ssh wisherful`) betreibt mehrere Seiten mit nginx, PHP-FPM 8.4, PostgreSQL und Supervisor. Pronto bekommt einen
eigenen vHost, eine eigene Datenbank, eigene Supervisor-Programme (`pronto-worker`, `pronto-reverb`) und einen eigenen
Cron-Eintrag (`/etc/cron.d/pronto-scheduler`). Andere Seiten bleiben unberührt.

Erstinstallation (einmal, als `ubuntu`; das Repo ist privat, also Skript per `scp` auf den Server):

```bash
scp install.sh wisherful:~/install-pronto.sh
ssh wisherful bash install-pronto.sh
```

Updates:

```bash
ssh wisherful "cd /var/www/pronto && bash deploy.sh"
```

Stripe einrichten (Test- oder Live-Keys, erneut ausführbar):

```bash
ssh -t wisherful "cd /var/www/pronto && python3 server/stripe-setup.py"
```

Das Skript fragt die Keys verdeckt ab und legt über die Stripe-API den Webhook an (Secret landet direkt in `.env`),
registriert die Domain für Apple Pay und Google Pay, schaltet PayPal ein (falls für den Account verfügbar), legt den
Terminal-Standort für Tap to Pay an (fragt einmal nach der Adresse) und lädt die Konfiguration neu.

## Cassa-App (Tap to Pay on Android)

Eine Capacitor-App (`android/`, `capacitor.config.ts`) lädt `https://pronto.tiamo-pizza.de/team/counter` und bringt das
Stripe-Terminal-SDK mit (`@capacitor-community/stripe-terminal`, Stripe SDK 5.8.2). In der App gibt es an der Cassa den
Knopf "Karte (Tap to Pay)": Pronto legt die Bestellung an (offen, noch nicht in der Queue), Stripe bekommt den Betrag aus
der Bestellung (nie vom Handy), der Gast hält Karte, Handy oder Uhr an das Theken-Handy, danach ist die Bestellung bezahlt
und in der Queue. Klappt es nicht: "Nochmal versuchen", "Gast zahlt bar" oder "Bestellung abbrechen". Meldet sich die App
nach der Zahlung nicht mehr (Netz weg), erledigt der Stripe-Webhook den Rest. Im Browser bleibt alles wie bisher, "Karte
(Terminal)" mit SumUp bleibt in der App als Ersatz.

Code: `app/Services/Orders/TapToPayCheckout.php`, `app/Services/Payments/CardTerminal.php`,
`app/Http/Controllers/Team/TapToPayController.php`, `resources/js/lib/tapToPay.js`. Im Testmodus (Stripe-Testkeys)
simuliert Stripe den Tap, es wird nichts belastet. Gebühren live: 1,4 % + 0,10 € (EWR-Karten) plus 0,10 € je Tap to Pay.

**Handy-Voraussetzungen** (prüft Stripe beim Verbinden): Android 13 oder neuer, Sicherheitsupdate nicht älter als
12 Monate, NFC, Google Play Store (GMS), nicht gerootet, Bootloader gesperrt, Entwickleroptionen aus, Standort erlaubt.
Bekannt gut: Google Pixel 7a/8a/9a, Samsung Galaxy A15/A25/A35/A55. Ältere Lenovo-Tablets und -Handys haben meist
kein NFC oder kein Android 13.

**Installieren** (Team-Anleitung):

1. Auf dem Theken-Handy `https://pronto.tiamo-pizza.de/team/app` öffnen (Team-Login), "App herunterladen".
2. Download öffnen. Android fragt nach "Unbekannte Apps installieren": für den Browser erlauben, zurück, "Installieren".
3. App "TIAMO Pronto" öffnen, anmelden, Standort erlauben.
4. NFC einschalten (Einstellungen, "Verbundene Geräte" bzw. "Verbindungen", NFC).
5. Entwickleroptionen aus, sonst lehnt Stripe das Handy ab.

**Neue APK bauen** (nur bei nativen Änderungen; Web-Änderungen kommen wie immer über `deploy.sh`):

1. GitHub, Actions, "Android APK (Cassa-App)", "Run workflow", Version eintragen (z. B. `1.0.1`).
2. Das Artifact `tiamo-pronto-<version>` herunterladen, entpacken und auf den Server legen:

```bash
scp tiamo-pronto.apk wisherful:/tmp/
ssh wisherful "cd /var/www/pronto && php8.4 artisan android:publish-apk /tmp/tiamo-pronto.apk --app-version=1.0.1"
```

Signatur-Secrets im GitHub-Repo (Settings, Secrets, Actions), nie im Repo: `ANDROID_KEYSTORE_BASE64`,
`ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`. Den Keystore einmal erzeugen und sicher
aufbewahren (ohne ihn lassen sich installierte Apps nicht mehr aktualisieren):

```bash
keytool -genkeypair -v -keystore tiamo-pronto.jks -keyalg RSA -keysize 4096 -validity 10000 -alias pronto
base64 -w0 tiamo-pronto.jks
```

Lokal bauen (Android SDK nötig): `npx cap sync android && cd android && ./gradlew assembleDebug`. Ein Debug-Build läuft
nur mit Testkeys, Stripe lehnt sie live ab.

## Checkliste "Vor dem Markttag"

**Am Vortag**

- [ ] Admin: Event aktiv, Datum, Karte (Preise, Edition), Bar erlaubt?, Ofenplätze und Minuten pro Durchgang stimmen
- [ ] QR-Aufsteller gedruckt (Admin, Events, "QR-Code") und einmal mit dem Handy gescannt
- [ ] Testbestellung online (echte Karte, kleiner Betrag) und Storno mit Rückerstattung im Admin
- [ ] Tablets und Handys voll geladen, Powerbank, Ladekabel, Tablet-Ständer
- [ ] Tablets: automatische Sperre aus, Helligkeit hoch, Ton laut, Benachrichtigungen anderer Apps aus

**Am Stand**

- [ ] Router/Hotspot an, Tablets im Netz (mobiles Netz als Ersatz bereit)
- [ ] Queue-Tablet: `/team/queue` öffnen, "Tippen zum Starten" (Ton, Bildschirm bleibt an), oben steht "Live"
- [ ] Display-Tablet: Display-Link aus dem Admin öffnen, "Tippen zum Starten", quer in den Ständer
- [ ] Theken-Handy: App "TIAMO Pronto" öffnen (sonst `/team/counter` im Browser), NFC an, unter "Karte (Tap to Pay)"
      steht kein Fehler. Eine Probezahlung mit kleinem Betrag, danach im Admin stornieren (erstattet automatisch).
- [ ] Testbestellung per QR: erscheint in der Queue und auf dem Display, "Pronto!" klingelt auf dem Handy
- [ ] Probe: WLAN am Queue-Tablet kurz aus, zweimal tippen, WLAN wieder an. Die Statuswechsel kommen nach.
- [ ] Wechselgeld, SumUp-App als Ersatz für Kartenzahlung an der Theke

**Wenn etwas hakt**

- Ofen voll: in der Queue "Pause" (5 bis 30 Min). Gäste sehen "Kurze Pause", die Wartezeit steigt automatisch.
- Artikel alle: "Ausverkauft" in der Queue, sofort für alle Gäste grau.
- Tablet zeigt "Offline - wird nachgesendet": einfach weiterarbeiten, es gleicht sich selbst ab.
- Falsche Bestellung: Admin, Bestellungen, "Stornieren" (online bezahlt: Stripe erstattet automatisch).

## Datenschutz

Keine Tracker, nur technisch notwendige Cookies. Kontaktdaten für Benachrichtigungen werden nach 24 Stunden automatisch gelöscht
(`notifications:purge`). Bestellungen und Zahlungen werden nie gelöscht, nur storniert (Audit-Log in `order_events`).
Datenschutzseite: `/datenschutz` (Entwurf, vor dem Start prüfen lassen), das Impressum verweist auf tiamo-pizza.de.
