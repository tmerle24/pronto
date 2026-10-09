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

Danach einmalig im Stripe-Dashboard:

1. **Webhook** auf `https://pronto.tiamo-pizza.de/webhooks/stripe` mit den Ereignissen `payment_intent.succeeded` und
   `payment_intent.payment_failed`. Signing Secret als `STRIPE_WEBHOOK_SECRET` in `.env`, dann `bash deploy.sh --no-npm`.
2. **Zahlungsmethoden**: Karte, Apple Pay, Google Pay, PayPal aktivieren.
3. **Apple Pay**: Domain `pronto.tiamo-pizza.de` unter "Payment method domains" eintragen (Stripe prüft sie selbst).

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
- [ ] Theken-Handy: `/team/counter`
- [ ] Testbestellung per QR: erscheint in der Queue und auf dem Display, "Pronto!" klingelt auf dem Handy
- [ ] Probe: WLAN am Queue-Tablet kurz aus, zweimal tippen, WLAN wieder an. Die Statuswechsel kommen nach.
- [ ] Wechselgeld, SumUp-App für Kartenzahlung an der Theke

**Wenn etwas hakt**

- Ofen voll: in der Queue "Pause" (5 bis 30 Min). Gäste sehen "Kurze Pause", die Wartezeit steigt automatisch.
- Artikel alle: "Ausverkauft" in der Queue, sofort für alle Gäste grau.
- Tablet zeigt "Offline - wird nachgesendet": einfach weiterarbeiten, es gleicht sich selbst ab.
- Falsche Bestellung: Admin, Bestellungen, "Stornieren" (online bezahlt: Stripe erstattet automatisch).

## Datenschutz

Keine Tracker, nur technisch notwendige Cookies. Kontaktdaten für Benachrichtigungen werden nach 24 Stunden automatisch gelöscht
(`notifications:purge`). Bestellungen und Zahlungen werden nie gelöscht, nur storniert (Audit-Log in `order_events`).
Datenschutzseite: `/datenschutz` (Entwurf, vor dem Start prüfen lassen), das Impressum verweist auf tiamo-pizza.de.
