# Offen

Stand 10.10.2026. Erster Markt: 5./6.12.2026, Probelauf ca. 21.11.2026.

## Entscheidungen

- [x] **Stück Pizza: kommt es in den Ofen?** Entschieden 10.10.: bleibt wie es ist. Jede Bestellung mit Stück geht durch
  den Ofen (ein Knopf pro Schritt, notfalls zweimal tippen), Stück = 0,25 Ofeneinheiten.
- [x] **Theke: Stück oder Getränk direkt über den Tresen?** Gebaut 10.10.: Kasse hat "Direkt mitgeben" / "In die Küche".
  Nur Stücke und Getränke → automatisch "Direkt mitgeben" (sofort abgeholt, keine Nummer, nicht in Queue und Display,
  zählt in Umsatz und Beleg). Mit ganzer Pizza → "In die Küche". Umschaltbar mit einem Tap.
- [ ] **Bestellungen ohne Ofen (nur Getränke, Stück = 0) überspringen "Neu" und "Im Ofen"?** Vorschlag: landen direkt in
  "Pronto", Gast wird sofort benachrichtigt. Theke: reine Getränke-Bestellung gar nicht erst in die Queue. Noch nicht gebaut.
- [ ] **Klarna** in Stripe aus- oder anlassen?
- [ ] **PIN-Login** für schnellen Gerätewechsel an Tablets nötig?

## Vor dem Probelauf

- [ ] Stripe-Konto verifizieren (Live-Konto), dann `python3 server/stripe-setup.py` mit Live-Keys
- [ ] Testdaten vom Echtbetrieb trennen: Test-Bestellungen haben fortlaufende Belegnummern bekommen (2026-0000xx).
      Vor dem Live-Gang Belegzähler und Testbestellungen sauber trennen.
- [ ] Firmendaten für den Beleg: `BUSINESS_NAME`, `BUSINESS_ADDRESS`, `BUSINESS_TAX_NUMBER`, `BUSINESS_VAT_ID`
- [ ] Steuerberater: TSE nötig? Falls ja, fiskaly-Adapter gegen die Sandbox testen, dann `FISCAL_DRIVER=fiskaly`
- [ ] Datenschutzseite `/datenschutz` rechtlich prüfen lassen (ist als Entwurf markiert)
- [ ] Team-Zugänge im Admin anlegen (Timo, Papà, Samuel, Boris)
- [ ] Optional: WhatsApp (Nummer + Meta/360dialog-Zugang), SMS (seven.io API-Key)
- [ ] QR-Aufsteller drucken, optional NFC-Aufkleber ("Handy hier dranhalten") am Display-Tablet

## Im Probelauf auf echten Geräten prüfen

- [ ] Apple Pay / Google Pay mit Live-Keys
- [ ] Ton, Vibration, Bildschirm bleibt an (iOS und Android)
- [ ] Tablet bei totem Netz neu laden (Service Worker), Offline-Puffer
- [ ] WLAN/Hotspot am Stand

## Läuft separat

- [ ] Android-App mit Tap to Pay (eigene Session, Branch `feature/tap-to-pay`)
