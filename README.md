# DefectiveReturnStock

Aktuelle Version: `1.7.9`

PlentyONE-Backend-Plugin für eine Flow-Aktion, die eine bereits automatisch
eingebuchte defekte Retoure wieder aus dem Bestand entfernt.

## Technisches Verhalten

- Die Aktion verarbeitet ausschließlich Retouren.
- Die Ausbuchung erfolgt über die variantenbezogene Bestandsfunktion für Lager
  `1`. Diese Schnittstelle erwartet die Buchungsdaten als flaches Objekt und
  liefert den aktualisierten Variantenbestand zurück. Vor und nach dem
  Buchungsaufruf wird zusätzlich der physische Aggregatbestand des Lagers
  geprüft.
- Der Plenty-Bestandsbuchung werden alle Pflichtangaben übergeben:
  Buchungszeitpunkt, Währung, Wechselkurs, Auftragsnummer, Menge und Grund.
- Der Buchungszeitpunkt wird unmittelbar vor der Ausbuchung im von Plenty
  verlangten W3C-Format erzeugt.
- Als Bestandsgrund wird `207` (Defekt) verwendet.
- Es wird keine Lagerort-ID übertragen. Plenty bucht dadurch aus dem
  Standard-Lagerort des Lagers aus.
- Bei Set-Artikeln werden die bestandsgeführten Komponenten verarbeitet;
  Set-Hauptpositionen werden nicht an die Bestandsfunktion gesendet.
- Vor jeder Ausbuchung prüft das Plugin die tatsächlichen Warenbewegungen auf
  eine bereits vorhandene Ausbuchung mit derselben Retouren-ID, Variante und
  dem Grund `207`. Positive Retouren-Einbuchungen werden nicht als erfolgreiche
  Ausbuchung behandelt. Wird bereits eine Ausbuchungsbewegung gefunden, nennt
  der Flow Tracker die betroffene Variante ausdrücklich.
- Die Aktion endet nur erfolgreich, wenn Plenty nach dem Buchungsaufruf einen
  um die Retourenmenge verminderten physischen Bestand zurückliefert. Ein
  unveränderter Bestand erzeugt eine konkrete Meldung mit Vorher-/Nachher-Wert.
- Version `1.7.9` verwendet dafür ausdrücklich
  `VariationStockRepositoryContract::bookOutgoingItems()` mit der Variante als
  Ziel. Die zuvor verwendete lagerweite Schnittstelle nahm die REST-Verpackung
  an, führte in diesem System aber keine Warenbewegung aus.

## Einrichtung

Die V3 besitzt die neue technische Kennung `DefectiveReturnStock::book-out-v3`, damit Plenty keine alte Flow-Beschreibung aus dem Cache verwendet.


1. Das Plugin dem Plugin-Set hinzufügen und bereitstellen.
2. Im Flow Studio die bestehende Aktion
   `Defekte Retoure aus Lagerort ausbuchen (V3)` im Zweig für defekte Retouren
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
erfolgreich ausgeführte Positionen werden anhand ihrer Warenbewegungen erkannt
und im Flow Tracker eindeutig gemeldet. Für die Prüfung muss kein neuer Auftrag
und keine neue Gutschrift erstellt werden.
