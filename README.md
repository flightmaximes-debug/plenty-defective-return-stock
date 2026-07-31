# DefectiveReturnStock

Aktuelle Version: 1.1.7

PlentyONE-Backend-Plugin für eine Flow-Aktion, die alle bestandsrelevanten
Positionen einer defekten Retoure aus einem fest konfigurierten
Retouren-Lagerort ausbucht.

Aktuelle Version: `1.1.2`

## Installation

1. Dieses Verzeichnis als Git-Repository für ein PlentyONE-Plugin bereitstellen.
2. Das Plugin dem gewünschten Plugin-Set hinzufügen und das Set bereitstellen.
3. In der Plugin-Konfiguration die ID des Retourenlagers und die ID des
   Retouren-Lagerorts eintragen.
   Für den automatisch vorhandenen Standard-Lagerort ist die Lagerort-ID `0`.
4. Als Buchungsgrund normalerweise `Warenausgang Defekt (207)` verwenden.
5. In Flow Studio die Plugin-Aktion
   `Defekte Retoure aus Retouren-Lagerort ausbuchen` direkt hinter den
   Statuswechsel auf 9.7 setzen.

Die Aktion ist ab Version 1.1.0 als native PlentyONE-Flow-Aktion registriert.
Nach einem Update von 1.0.x muss ein bereits vorhandener Aktionsblock im Flow
entfernt und durch die neu geladene Plugin-Aktion ersetzt werden.

Ab Version 1.1.1 steht dieselbe Funktion zusätzlich als klassische
Ereignisaktion in der Aktionsgruppe `Retoure` zur Verfügung. Dadurch kann die
Ausbuchung alternativ über einen Statuswechsel auf 9.7 oder 9.12 ausgelöst
werden, ohne das für Flow verwendete Mandanten-Plugin-Set zu wechseln.

## Verhalten

- Die Aktion verarbeitet ausschließlich Aufträge vom Typ Retoure.
- Verarbeitet werden normale Varianten sowie Bundle- und Set-Komponenten.
- Die Retourenmenge wird immer als positive Ausbuchungsmenge verwendet.
- Vor der Buchung wird der Bestand jeder Variante auf dem konfigurierten
  Lagerort geprüft.
- Reicht der Bestand für eine Position nicht aus, wird nichts absichtlich
  teilweise gebucht; die Aktion meldet einen Fehler.
- Jede Retouren-ID erhält einen dauerhaften Verarbeitungsmarker. Erfolgreiche
  oder bereits laufende Retouren werden nicht erneut ausgebucht.
- Fehlgeschlagene Retouren können durch eine erneute Flow-Ausführung erneut
  verarbeitet werden.

## Protokollierung

Erfolg, Fehler und übersprungene Doppelausführungen werden im PlentyONE-Log
unter der Integration `DefectiveReturnStock` mit der Retouren-ID als Referenz
protokolliert.

## Wichtiger Test vor dem Produktivbetrieb

Das Plugin sollte zunächst in einem separaten Plugin-Set mit einer Testretoure
geprüft werden. Dabei insbesondere kontrollieren:

- Kommt die Plugin-Aktion in Flow Studio unter der Gruppe Plugins an?
- Wird die Ware vor der Plugin-Aktion tatsächlich auf den konfigurierten
  Lagerort eingebucht?
- Entspricht die Retourenmenge der erwarteten Ausbuchungsmenge?
- Wird eine zweite Ausführung derselben Retoure ohne weitere Bestandsbewegung
  beendet?
