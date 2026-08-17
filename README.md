# DefectiveReturnStock

Aktuelle Version: `1.5.2`

PlentyONE-Backend-Plugin für eine Flow-Aktion, die eine bereits automatisch
eingebuchte defekte Retoure wieder aus dem Bestand entfernt.

## Technisches Verhalten

- Die Aktion verarbeitet ausschließlich Retouren.
- Sie ermittelt je Retourenposition den wirklich vorhandenen Bestand in
  Lager `1` am Standard-Lagerort.
- Die Ausbuchung erfolgt positionsweise über die Varianten-Bestandsfunktion
  mit dem Bestandsgrund `207` (Defekt).
- Der virtuelle Standard-Lagerort wird anhand seiner Plenty-ID `0` erkannt.
  Diese ID wird bei der Ausbuchung bewusst nicht an Plenty gesendet.
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
`Lager 1` festgelegt; der Standard-Lagerort wird anhand des realen Bestands
ermittelt.

## Sicher testen

Für einen erneuten Test ist kein neuer Auftrag und keine neue Retoure nötig.
Eine vorhandene defekte Retoure kann den Flow erneut durchlaufen. Bereits
erfolgreich ausgeführte Positionen werden anhand interner Kennungen erkannt
und übersprungen.
