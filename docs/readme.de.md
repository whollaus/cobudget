# CoBudget

[English](../README.md) · **Deutsch** · [Français](readme.fr.md) · [Español](readme.es.md)

**Ausgaben verstehen. Kosten fair teilen. Den Überblick in Nextcloud behalten.**

CoBudget ist ein Haushaltsbuch für Einzelpersonen, Paare, Familien und WGs.
Erfasse Einnahmen und Ausgaben, plane kommende Zahlungen und sieh, welchen Anteil
du an gemeinsamen Kosten trägst. Gemeinsame Bereiche helfen beim Haushalt, auf
Reisen oder in kleinen Gruppen. Belege und Erinnerungen bleiben bei den Zahlungen,
zu denen sie gehören.

> [!WARNING]
> **Beta — aktueller Entwicklungsstand: 0.8.8.** Erstelle regelmäßig Backups und
> verwende CoBudget nicht als einzige Aufzeichnung wichtiger Finanzdaten.
> Die Entwicklung ist KI-unterstützt. Automatisierte Tests ersetzen keine
> unabhängige Sicherheitsprüfung; diese steht noch aus. Siehe [Sicherheit](../SECURITY.md).

## Dein Alltag, alleine oder gemeinsam

- **Überblick behalten:** Einnahmen und Ausgaben mit Kategorien, Zahlungspartnern,
  Labels und Tags erfassen.
- **Vorausplanen:** wiederkehrende Zahlungen, Budgetziele, Auswertungen, Vergleiche
  und Prognosen nutzen.
- **Fair aufteilen:** festhalten, wer bezahlt hat, Anteile wählen und offene Salden
  abrechnen. Deine persönliche Übersicht zählt deinen Anteil; der gemeinsame
  Bereich zeigt den Gesamtbetrag.
- **Zusammenhänge behalten:** Belege in Nextcloud Files ablegen und eigene oder
  zahlungsbezogene Erinnerungen verwalten, erledigen und archivieren.
- **Benachrichtigungen wählen:** Nextcloud, privater CoBudget-Chat in Talk und
  optionale E-Mails lassen sich je unterstütztem Ereignis getrennt aktivieren.
  Erinnerungen können mit einem Kalender synchronisiert werden; Änderungen in
  privaten Kalendern werden auch nach CoBudget übernommen.
- **Daten weiterverwenden:** Berichte drucken, CSV exportieren, Tabellen in eine
  Tabellenkalkulation kopieren und persönliche Exporte oder Admin-Backups erstellen.
- **Passend einrichten:** getrennte Datenbestände mit Workspaces, mobile und
  Desktop-Ansicht sowie helles, dunkles oder Systemdesign.

Die Oberfläche ist auf Deutsch, Englisch, Französisch und Spanisch verfügbar.
Die weiterführende technische Dokumentation ist auf Englisch:
[Funktionsübersicht](../FEATURES.md), [Auswertungen](reports.md) und
[Konzept von CoBudget](concept.md).

## Wie CoBudget aufgebaut ist

Im Mittelpunkt stehen Zahlungen, persönliche Anteile und gemeinsame Bereiche.
Dein persönliches Haushaltsbuch zeigt deine eigenen Kosten; der gemeinsame Bereich
hält den Gesamtbetrag fest, wer bezahlt hat und wer wem noch etwas schuldet.
CoBudget verbindet sich nicht mit Bankkonten und synchronisiert keine Bankumsätze.
Die Auswertungen beruhen auf den erfassten Zahlungen und deren Aufteilung. Ihr Saldo
ist eine Haushaltsübersicht und kein abgeglichener Bankkontostand.

## Voraussetzungen und Version 1.0

Die aktuelle Beta unterstützt **Nextcloud 33–35**. **CoBudget 1.0 setzt mindestens
Nextcloud 34 voraus. Die Unterstützung für Nextcloud 33 endet mit der 0.x-Reihe.**
Für 1.0 sind Tests mit Nextcloud 34 und 35 vorgesehen; ein Termin steht noch nicht fest.

Benötigt werden eine passende PHP-Version (ab 8.2 für Nextcloud 33/34, ab 8.3 für 35),
ein moderner Browser und funktionierende Nextcloud-Hintergrundaufgaben. Talk,
Kalender und E-Mail sind optional. E-Mails gehen an die Adresse im Nextcloud-Konto;
der Server muss für den Mailversand eingerichtet sein.

Der [Prüfbericht für 1.0](release-readiness-1.0.md) nennt behobene Probleme und offene
Freigabekriterien. Die [Testübersicht](../tests/README.md) beschreibt die Abdeckung.

## Installation und Updates

Installiere CoBudget über die App-Verwaltung aus dem
[Nextcloud App Store](https://apps.nextcloud.com/apps/cobudget).
Administratoren können auch `occ app:install cobudget` verwenden.
Für die manuelle Installation:

1. Die signierte Datei `cobudget.tar.gz` aus den [GitHub Releases](https://github.com/whollaus/cobudget/releases) herunterladen.
2. Als Ordner `cobudget` nach `apps/` oder `custom_apps/` der Nextcloud entpacken.
3. Die App aktivieren und ein eventuell anstehendes Update abschließen.

Die automatisch von GitHub erzeugten Quellcodearchive sind keine installierbaren
App-Pakete. Vor Updates ein Backup erstellen. Nextclouds `cron.php` sollte alle fünf
Minuten als Webserver-Benutzer laufen. Details stehen im [Betriebshandbuch](operations.md).

## Was wird gesichert?

Ein **persönlicher Export** bewahrt deine eigene finanzielle Sicht. Er lässt sich
nur in einen leeren CoBudget-Benutzerbestand importieren; gemeinsame Anteile werden
dabei eigenständige persönliche Zahlungen. Ein **Admin-Vollbackup** enthält alle
CoBudget-Finanzdaten und übertragbare Benutzereinstellungen. Seine Wiederherstellung
ersetzt den aktuellen Datenbestand der App.

**Belegdateien sind in keinem der Archive enthalten, nur ihre Pfade.** Sichere die
zugehörigen Dateien mit deiner vorhandenen Backup-Lösung für Nextcloud Files.
Die Belegsicherung bleibt bewusst deren Aufgabe. Die Archive ersetzen kein
vollständiges Nextcloud-Backup. Während Backup und Restore sperrt CoBudget den
App-Zugriff vorübergehend. Läuft noch ein Vorgang, starte die Sicherung oder
Wiederherstellung danach erneut. Lade offene CoBudget-Tabs nach einem Restore neu.
Siehe [Wiederherstellung](operations.md#backup-and-recovery).

## Rückmeldungen und Unterstützung

Fehlermeldungen und Testergebnisse helfen besonders während der Beta. CoBudget wird
hauptsächlich von einer Person gepflegt; Codebeiträge bitte vorher absprechen.
[GitHub Issues](https://github.com/whollaus/cobudget/issues) · [Mitwirken](../CONTRIBUTING.md) ·
[Änderungen](../CHANGELOG.md) · [Sicherheitsprobleme melden](../SECURITY.md).

Entwicklung unterstützen: [GitHub Sponsors](https://github.com/sponsors/whollaus)
oder [Stripe](https://donate.stripe.com/aFaeVdfARbPNe897bu5J600).
Lizenz: [AGPL-3.0 oder neuer](../LICENSE).
