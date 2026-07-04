# CoBudget Test Plan

Dieses Verzeichnis ist der Startpunkt fuer automatisierte Backend-Tests. Die App hat weiterhin kein PHPUnit-/Nextcloud-Test-Bootstrap im Repo; deshalb ist die erste Suite bewusst dependency-frei gehalten und prueft die wichtigsten Backend-Vertraege direkt mit PHP.

## Aktuell ausfuehrbar

```sh
php tests/php/run.php
npm run test:php
php tests/static-security.php
npm run test:frontend-smoke
npm test
```

`tests/php/run.php` ist der schnelle Backend-Regressionslauf. Er prueft:

- Routen zeigen auf existierende Controller-Methoden und haben eindeutige Verb-/URL-Paare.
- User-Daten-API-Methoden liefern JSON-Fehler und pruefen Auth-/Workspace-Header-Fehler.
- `WorkspaceAwareTrait` validiert Workspace-Header, Pflichtnamen, Typen, Betraege und Integer-Cents.
- Entry-, Projekt-, Kategorie-, Empfaenger-, Template-, Workspace- und Cron-Vertraege bleiben workspace-scoped.
- Kritische Operationen bleiben transaktional.

`tests/static-security.php` prueft weiterhin grob, ob die zentralen Workspace-Guards, strikte `X-Workspace-Id`-Fehler, User-/Workspace-Scope bei kritischen Controllern, Transaktionen und `amount_cents`-Migrationen im Code vorhanden sind.

Der Frontend-Smoke-Test prueft ohne Browser-Setup, ob die Vue-Routen lazy geladen werden, das globale Zahlungsmodal erst bei Nutzung geladen wird, keine veralteten `::v-deep`-Selectoren mehr im Sourcecode stehen, keine grossen `@nextcloud/vue`-Barrel-Imports zurueckkommen und die native Nextcloud-App-Shell erhalten bleibt.

## Prioritaet 1: Workspace-Isolation

- User A kann keine Eintraege, Projekte, Kategorien, Empfaenger oder Vorlagen von User B lesen, aendern oder loeschen.
- Ein aktiver Workspace A darf keine IDs aus Workspace B fuer Eintraege, Vorlagen oder Projektoperationen verwenden.
- Projektoperationen schlagen fehl, wenn das Projekt nicht im aktiven Workspace liegt.
- Workspace-Loeschung loescht nur Daten des authentifizierten Users im betroffenen Workspace.

## Prioritaet 2: Referenz- und Nutzungsregeln

- Kategorie-/Empfaenger-Loeschung ist blockiert, wenn die ID im aktuellen Workspace verwendet wird.
- Globale Kategorien und Empfaenger sind nutzbar, aber nicht als persoenliche Eintraege loeschbar.
- Templates akzeptieren nur Projekt-, Kategorie- und Empfaenger-IDs, die im aktiven Workspace erreichbar sind.

## Prioritaet 3: Kritische Operationen

- Projektanlage schreibt Projekt und Mitglieder atomar.
- Projektloeschung entfernt Mitglieder und Projekt atomar.
- Projektabrechnung markiert nur Eintraege des aktiven Workspace als abgerechnet.
- Wiederkehrende Jobs erzeugen Folgeeintraege mit der bestehenden `workspace_id`.
- Geldwerte werden intern ueber `amount_cents` als Integer-Cents gespeichert und berechnet.

## Manuelle Verifikation

- Zwei Workspaces erstellen.
- In Workspace A Eintrag, Kategorie, Empfaenger, Vorlage und Projekt anlegen.
- Zu Workspace B wechseln und pruefen, dass A-Daten nicht sichtbar oder nutzbar sind.
- Projektmitgliedschaft und Abrechnung testen.
- Einen nicht-default Workspace loeschen und pruefen, dass der andere Workspace unveraendert bleibt.
