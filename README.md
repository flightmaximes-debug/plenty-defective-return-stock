# DefectiveReturnStock

Aktuelle Version: `1.8.0`

PlentyONE-Backend-Plugin für eine Flow-Aktion, die eine bereits automatisch
eingebuchte defekte Retoure wieder aus dem Bestand entfernt.

## Technisches Verhalten

- Die Aktion verarbeitet ausschließlich Retouren.
- Die Ausbuchung erfolgt über
  `VariationStockRepositoryContract::bookOutgoingItems()` für Lager `1`.
- Die Ausbuchungsmenge wird negativ übergeben. Das ist bei dieser
  variantenbezogenen Plenty-Bestandsschnittstelle für eine ausgehende Buchung
  erforderlich.
- Der Standard-Lagerort wird ausdrücklich mit `storageLocationId = 0`
  übergeben.
- Als Bestandsgrund wird `207` (Defekt) verwendet.
- Buchungszeitpunkt, Währung, Wechselkurs und Retouren-ID werden mitgegeben.
- Bei Set- und Bundle-Artikeln werden ausschließlich die bestandsgeführten
  Komponenten verarbeitet; Hauptpositionen werden nicht zusätzlich gebucht.
- Vor der Ausbuchung prüft das Plugin anhand der Warenbewegungen, ob für
  Retoure, Variante und Grund `207` bereits eine negative Bewegung existiert.
- Nach der Buchung muss der physische Aggregatbestand des Lagers um die
  Retourenmenge gesunken sein. Andernfalls schlägt die Aktion mit konkretem
  Vorher-/Nachher-Wert fehl.

## Einrichtung

1. Version `1.8.0` im vorhandenen Plugin-Set aktualisieren und bereitstellen.
2. Die bereits vorhandene Flow-Aktion
   `Defekte Retoure aus Lagerort ausbuchen (V3)` unverändert weiterverwenden.
3. Die Aktion steht nach dem Schritt, der die defekte Retoure erstellt und die
   automatische Retouren-Einbuchung abgeschlossen hat.
4. Dieselbe Aktion kann für Status `9.7` und Otto-Status `9.12` verwendet
   werden, sofern die jeweilige Retoure nur defekte Positionen enthält.

Die technische Kennung bleibt `DefectiveReturnStock::book-out-v3`. Der Flow
muss deshalb nicht neu aufgebaut werden. Lager und Lagerort sind fest auf
`Lager 1` und den `Standard-Lagerort (ID 0)` eingestellt.

## Sicher testen

Für den Test ist kein neuer Auftrag und keine neue Retoure nötig. Die bereits
vorhandene Retoure kann im manuellen Test-Flow erneut verwendet werden.
Erfolgreiche Ausbuchungen werden anhand ihrer Warenbewegung erkannt und nicht
doppelt ausgeführt.
