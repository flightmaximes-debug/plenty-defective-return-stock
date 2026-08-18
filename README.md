# DefectiveReturnStock

Aktuelle Version: `1.7.4`

PlentyONE-Backend-Plugin für eine Flow-Aktion, die eine bereits automatisch
eingebuchte defekte Retoure wieder aus dem Bestand entfernt.

## Technisches Verhalten

- Die Aktion verarbeitet ausschließlich Retouren.
- Die Ausbuchung erfolgt ohne fehleranfällige Bestandsvorabfrage direkt über
  die Varianten-Bestandsfunktion in Lager `1`.
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
  dem Grund `207`. Dabei werden ausschließlich negative Warenbewegungen als
  vorhandene Ausbuchungen gewertet. Positive Retouren-Einbuchungen lösen die
  Ausbuchung weiterhin aus. Dadurch wird eine doppelte Ausbuchung verhindert,
  ohne eine Auftragsnotiz anlegen zu müssen.

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
und übersprungen.
