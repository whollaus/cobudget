# Zahlungen importieren

[English](payment-imports.md) · **Deutsch** · [Français](payment-imports.fr.md) · [Español](payment-imports.es.md)

Aktiviere **Einstellungen → Funktionen → Zahlungsimport**, um beide Importwege zu nutzen. Die Funktion ist standardmäßig ausgeschaltet. Beim Ausschalten pausieren die Ordnerimporte. Einstellungen und Buchungsstand bleiben gespeichert. Beim erneuten Einschalten laufen die aktiven Ordnerimporte weiter.

Für einen einmaligen Import verwendest du den **Bulk-Import**. Für regelmäßige Importe kannst du einen Ordner einrichten. Beide verwenden dasselbe JSON-Format. Bei Ausgaben bist du der Zahler, bei Einnahmen der Empfänger. In gemeinsamen Bereichen gilt die übliche Aufteilung des Bereichs.

## Dateiformat

Trage jede Zahlung als eigenes Objekt in die Liste `payments` ein. Nur `date` und `amount` sind Pflichtfelder. Pro Datei sind bis zu 5.000 Zahlungen und 5 MiB (5.242.880 Byte) möglich. Beträge gelten in deiner Kontowährung. Beim automatischen Import gilt für die gesamte Datei die Kontowährung zum Zeitpunkt der ersten erfolgreichen Teilprüfung. Das gilt auch, wenn du die Währung während des Imports änderst. Für Einnahmen oder Zahlungen mit zukünftigem Datum muss die jeweilige Funktion in deinen Einstellungen aktiviert sein.

Das Beispiel enthält einen Lebensmitteleinkauf und einen Gehaltseingang. Passe Datum und Namen an deine Daten an. Unter **Importformat** kannst du ein Beispiel mit dem heutigen lokalen Datum kopieren oder herunterladen.

```json
{
  "payments": [
    {
      "date": "2026-11-19",
      "amount": -42.75,
      "category": "Groceries",
      "payment_partner": "Corner Market",
      "description": "Weekly shopping #household",
      "review": 1
    },
    {
      "date": "2026-11-19",
      "amount": 1850.00,
      "category": "Salary",
      "payment_partner": "Employer",
      "description": "November salary"
    }
  ]
}
```

| Feld | Bedeutung |
| --- | --- |
| `date` | Zahlungsdatum im Format `YYYY-MM-DD`. |
| `amount` | Betrag ungleich null mit bis zu zwei Nachkommastellen. Negativ für Ausgaben, positiv für Einnahmen. Dezimaltrennzeichen ist der Punkt. |
| `category` | Name der Kategorie für den Zahlungstyp, bis zu 128 Zeichen. |
| `payment_partner` | Name des Empfängers oder Zahlers für den Zahlungstyp, bis zu 128 Zeichen. |
| `description` | Text mit bis zu 512 Zeichen, auch mit Hashtags wie `#household`. |
| `important` | Wichtige Zahlung. |
| `review` | Zahlung prüfen. |
| `fixed_cost` | Fixkosten (nur Ausgaben). |
| `children` | Zahlung für Kinder. |
| `subscription` | Abo (nur Ausgaben). |
| `tax_relevant` | Steuerlich relevante Zahlung. |
| `insurance` | Versicherungszahlung. |

Die Labels von `important` bis `insurance` akzeptieren `1` (ein) oder `0` (aus). Ausgelassene Labels sind aus. CoBudget berücksichtigt die in deinen Einstellungen aktivierten Labels.

## Manueller Bulk-Import

1. Öffne **Bulk-Import**. Wähle für persönliche Zahlungen **Ohne Bereich** oder wähle einen gemeinsamen Bereich.
2. Wähle die JSON-Datei aus oder füge ihren Inhalt ein. Klicke auf **Vorschau anzeigen**.
3. Prüfe Beträge, Daten und eventuell neu anzulegende Kategorien oder Zahlungspartner. Bestätige den Import, um die Zahlungen zu buchen.

Bei persönlichen Zahlungen oder als Bereichsinhaber kannst du fehlende Kategorien und Zahlungspartner beim Import anlegen lassen. Andere Mitglieder verwenden vorhandene Namen für den jeweiligen Zahlungstyp oder lassen die Felder weg. Ein unbekannter Name führt bei der Prüfung zur Ablehnung der gesamten Datei. Unter **Importformat** findest du die im gewählten Bereich verfügbaren Namen.

Nach einem Abbruch kannst du dieselben Daten für dasselbe Ziel erneut prüfen. Die Vorschau zeigt die noch offenen Zahlungen.

## Automatischer Ordnerimport

1. Öffne **Einstellungen → Automatische Importe**. Wähle in der Zeile des gewünschten Ziels einen Ordner aus Nextcloud Files. Beim Öffnen der Einstellungen wird `CoBudget/Import` als Ausgangsordner angelegt. Verwende für jedes Ziel einen eigenen Ordner. Er bleibt auch bei pausiertem Import reserviert.
2. Wähle das Intervall (alle 5 oder 15 Minuten, stündlich oder täglich) und die Benachrichtigung (aus, nur bei Fehlern oder nach jedem Import). Schalte **Aktiv** ein. Sobald ein Import aktiv ist, kannst du unter **Einstellungen → Benachrichtigungen** je nach Server Nextcloud, E-Mail oder deinen persönlichen Talk-Chat auswählen.
3. Lege vollständige `.json`-Dateien direkt in den gewählten Ordner. Die Nextcloud-Hintergrundaufgaben prüfen ihn im gewählten Intervall. Mit dem Startsymbol neben **Aktiv** prüfst du ihn sofort. Große Importe können mehrere Durchläufe benötigen.
4. Bewege den Mauszeiger auf den Zeitpunkt unter **Ausgeführt**, fokussiere ihn per Tastatur oder tippe ihn an. Ein rotes Symbol kennzeichnet einen Fehler oder eine wartende Datei. Verarbeitete Dateien landen im Unterordner `Verarbeitet`, abgelehnte in `Fehler`. Klicke auf den Dateinamen, um die Datei in einem neuen Tab zu öffnen.

Ordnerimporte setzen die Dateisperre von Nextcloud voraus. Kann CoBudget eine Datei nicht lesen oder verschieben, bleibt sie im Eingangsordner. Ändert sich eine Quelldatei während der Buchung und kann CoBudget ihre neue Fassung wegen einer Sperre oder fehlender Leserechte nicht prüfen, warten neue Lieferungen in diesem Ordner. Ist nur das Verschieben blockiert, kann CoBudget Kopien der geänderten Fassung erkennen und ablehnen. Andere Dateien werden weiter importiert.

Wird ein Bereich archiviert, verschwindet sein Ordnerimport aus der Liste und pausiert. Nach dem Wiederherstellen gelten die bisherigen Einstellungen. Eine aktive Regel läuft bei der nächsten Prüfung weiter.

Dateien, die während einer Prüfung hinzukommen oder geändert werden, prüft CoBudget in einem späteren Durchlauf. Du kannst weitere Dateien hochladen, während ein Import läuft. Ändert die Synchronisierung die Zahlungsdaten beim Verschieben einer gebuchten Datei nach `Verarbeitet`, verschiebt CoBudget die geänderte Datei nach `Fehler` und lehnt weitere Kopien ab. Auch nach einem Abbruch lehnt CoBudget Kopien dieser geänderten Zahlungen ab, selbst wenn du die archivierte Datei inzwischen in einen anderen Ordner verschoben hast.

Ist ein Dateiname im Archiv schon vergeben, bleiben beide Dateien erhalten. Korrigierst du eine abgelehnte Datei, bevor sie nach `Fehler` verschoben wird, prüft CoBudget die korrigierte Datei bei einem späteren Durchlauf. Belegt nach einem Abbruch eine andere Datei den vorgesehenen Archivpfad, behält sie ihren Namen und kann weiterhin importiert werden. Das gilt auch, wenn die Synchronisierung die archivierte Datei überschreibt und Nextcloud ihre Datei-ID beibehält.

### Dateinamen, Duplikate und Korrekturen

Wähle für jede neue Zahlungsdatei **vor dem ersten Hochladen** einen neuen Namen, etwa `payments-2026-11-20.json`. Änderst du in einer bereits importierten Datei ein Zahlungsdatum und lädst sie unter demselben Namen hoch, lehnt CoBudget die geänderte Datei ab. Die Zahl in der Meldung gibt an, wie viele Zahlungen aus der Originaldatei gebucht wurden. Auch nach dem Umbenennen bleibt die abgelehnte Kopie gesperrt. Bereits gebuchte Zahlungen korrigierst du direkt in CoBudget. Wenn du eine Datei während der Buchung im Eingangsordner umbenennst, bleiben der ursprüngliche und der neue Dateiname reserviert.

Wurde ein Entwurf schon bei der Prüfung abgelehnt, kannst du ihn korrigieren und seinen Dateinamen erneut verwenden. Wird eine Fassung später wegen eines bereits gebuchten Dateinamens abgelehnt, bleiben Kopien dieser Fassung gesperrt.

Wenn du dieselben Zahlungen mit demselben Konto, Workspace und Ziel erneut hochlädst, bleiben die vorhandenen Buchungen erhalten. Das gilt auch bei einem anderen Dateinamen oder einer anderen Feldreihenfolge innerhalb einer Zahlung. Behalte bei einem erneuten Versuch die Reihenfolge der Zahlungen bei. Überschneiden sich zwei Exporte, entferne bereits gelieferte Zahlungen vor dem Hochladen der neuen Datei.

Fällt nach Buchungsbeginn eine Kategorie oder ein Zahlungspartner weg, zeigt **Ausgeführt** die wartende Datei, den fehlenden Namen und den Buchungsstand. Andere Dateien laufen weiter. Bitte den Bereichsinhaber, den ursprünglichen Namen wiederherzustellen. CoBudget setzt die gespeicherte Datei fort und bucht die offenen Zahlungen samt Anteilen.

Wurde der Eingangsordner gelöscht und unter demselben Pfad neu angelegt, wähle ihn unter **Einstellungen → Automatische Importe** erneut aus. Sobald der Import aktiv ist, kann CoBudget die gespeicherten Zahlungen auch bei leerem Ersatzordner fertig buchen.
