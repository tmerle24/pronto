# Offen

Stand 10.10.2026. Erster Markt: 5./6.12.2026, Probelauf ca. 21.11.2026.

## Entscheidungen

- [ ] **Stück Pizza: kommt es in den Ofen?** Stand 10.10.: Stücke kommen aus ganzen Pizzas. Liegt ein fertiges Stück
  bereit, geht es ohne Ofen raus; sonst muss eine ganze Pizza gebacken werden (ergibt 4 Stücke). Noch offen, wie das
  am Stand konkret läuft. Zwei Lösungen:
  1. **Timo entscheidet pro Bestellung:** reine Stück-Bestellungen haben in "Neu" groß "Fertig" (Stück liegt bereit)
     und kleiner "In den Ofen" (muss gebacken werden). Einfach, aber zwei Knöpfe statt einem. Favorit für den ersten Markt.
  2. **Stück-Vorrat zählen:** Timo tippt "+4 Margherita-Stücke", wenn er eine Pizza schneidet; Stück-Bestellungen
     nehmen aus dem Vorrat und gehen direkt auf Pronto, bei leerem Vorrat in den Ofen. Genauere Wartezeit, aber
     Timo muss den Vorrat pflegen.
  Zurzeit: Stück = 0,25 Ofeneinheiten, jede Bestellung geht durch den Ofen (Admin, Karte, Feld "Ofeneinheiten").
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
