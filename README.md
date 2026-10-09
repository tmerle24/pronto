# TIAMO Pronto

Bestell- und Abholsystem für den mobilen Pizza-Stand TIAMO Pizza: Gäste bestellen und bezahlen per QR-Code am Handy,
das Team arbeitet die Bestellungen am Tablet ab, vorne zeigt ein Display die Abholnummern.

## Lokal starten

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
docker compose up -d            # Postgres auf Port 5447
php artisan migrate --seed      # Karte, Demo-Event, Demo-Logins
npm run build
php artisan serve
```

Demo-Logins (nur lokal, aus `database/seeders/DemoSeeder.php`): `admin@pronto.test` und `cucina@pronto.test`.

Ausführliche Doku zu ENV-Variablen, Deployment und der Checkliste "Vor dem Markttag" folgt mit Meilenstein 8.
