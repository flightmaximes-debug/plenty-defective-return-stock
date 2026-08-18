# DefectiveReturnStock

Aktuelle Version: `1.6.0`

PlentyONE-Backend-Plugin für eine Flow-Aktion, die eine bereits automatisch
eingebuchte defekte Retoure wieder aus dem Bestand entfernt.

## Technisches Verhalten

- Die Aktion verarbeitet ausschließlich Retouren.
- Die Ausbuchung erfolgt ohne fehleranfällige Bestandsvorabfrage direkt über
  die Varianten-Bestandsfunktion in Lager `1`.
- Als Bestandsgrund wird `207` (Defekt) verwendet.
- Es wird keine Lagerort-ID übertragen. Plenty bucht dadurch aus dem
  Standard-Lagerort des Lagers aus.
- Bei Set-Artikeln werden die bestandsgeführten Komponenten verarbeitet;
  Set-Hauptpositionen werden nicht an die Bestandsfunktion gesendet.
- Interne, für Kunden unsichtbare Auftragskommentare verhindern eine doppelte
  Ausbuchung bei einer erneuten Flow-Ausführung.

## Einrichtung

1. Das Plugin dem Plugin-Set hinzufügen und bereitstellen.
2. Im Flow Studio die bestehende Aktion
   `Defekte Retoure aus Lagerort ausbuchen (V2)` im Zweig für defekte Retouren
   verwenden.
3. Die Aktion muss nach dem Schritt stehen, der die Retoure erstellt und den
   automatisch zurückgebuchten Bestand verfügbar gemacht hat.
4. Für Status `9.7` und Otto-Status `9.12` kann dieselbe Aktion verwendet
   werden, sofern die jeweilige Retoure ausschließlich defekte Positionen
   enthält.

Es gibt keine Plugin-Konfiguration für Lager oder Lagerort. Das Lager ist auf
`Lager 1` festgelegt; die Ausbuchung erfolgt am Standard-Lagerort.

## Sicher testen

Für einen erneuten Test ist kein neuer Auftrag und keine neue Retoure nötig.
Eine vorhandene defekte Retoure kann den Flow erneut durchlaufen. Bereits
erfolgreich ausgeführte Positionen werden anhand interner Kennungen erkannt
und übersprungen.
