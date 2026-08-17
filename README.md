# DefectiveReturnStock

Aktuelle Version: `1.4.0`

PlentyONE-Backend-Plugin für eine Flow-Aktion, die eine bereits automatisch
eingebuchte defekte Retoure wieder aus dem Bestand entfernt.

## Technisches Verhalten

- Die Aktion verarbeitet ausschließlich Retouren.
- Sie liest die von Plenty bereits gebuchten Eingangs-Transaktionen der
  Retoure aus.
- Für jede tatsächliche Einbuchung wird eine gleich große Gegenbuchung auf
  genau demselben Lagerort erstellt. Dadurch muss die ID des
  Standard-Lagerorts nicht geraten oder konfiguriert werden.
- Bei Set-Artikeln werden nur Positionen ausgebucht, für die Plenty wirklich
  Bestand eingebucht hat. Der nicht bestandsgeführte Set-Hauptartikel wird
  dadurch nicht fälschlich an die Bestands-API gesendet.
- Jede Gegenbuchung erhält eine eindeutige Kennung. Eine erneute Ausführung
  derselben Retoure bucht den Bestand deshalb nicht doppelt aus.
- Bereits vorbereitete, aber noch nicht gebuchte Gegenbuchungen werden bei
  einer Wiederholung fertiggestellt.

## Einrichtung

1. Das Plugin dem Plugin-Set hinzufügen und bereitstellen.
2. Im Flow Studio die bestehende Aktion
   `Defekte Retoure aus Lagerort ausbuchen (V2)` im Zweig für defekte Retouren
   verwenden.
3. Die Aktion muss nach der automatischen Retouren-Einbuchung stehen.
4. Für Status `9.7` und Otto-Status `9.12` kann dieselbe Aktion verwendet
   werden, sofern die jeweilige Retoure ausschließlich defekte Positionen
   enthält.

Es gibt keine Plugin-Konfiguration für Lager oder Lagerort. Das Lager ist auf
`Lager 1` festgelegt; der Lagerort wird aus der realen Einbuchung übernommen.

## Sicher testen

Für einen erneuten Test ist kein neuer Auftrag und keine neue Retoure nötig.
Eine vorhandene defekte Retoure kann den Flow erneut durchlaufen. Bereits
erfolgreich ausgeführte Gegenbuchungen werden anhand ihrer Kennung erkannt und
übersprungen.
