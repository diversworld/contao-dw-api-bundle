![Alt text](docs/dw-logo-kws.png?raw=true "Diversworld")

# Contao Diveclub API Bundle

API Bundle für die Kommunikation zwischen Contao und einer iOS App im Rahmen des Diveclub Managers.

## Features

- **Events:** Abfrage von Tauchkursen und Terminen.
- **Reservierungen:** Verwaltung von Buchungen (Ansehen und Erstellen).
- **Ausrüstung:** Zugriff auf Leihausrüstung (Jackets, Anzüge, etc.).
- **Tauchflaschen & Atemregler:** Spezielle Endpunkte für Flaschen und Regler.
- **TÜV-Checks:** Übersicht über anstehende Revisionen und Prüfvorschläge.
- **Schüler:** Verwaltung von Kursteilnehmern.
- **JSON-Format:** Alle Antworten sind für die einfache Integration in iOS optimiert.

## API Endpunkte

Alle Endpunkte befinden sich unter dem Präfix `/api`.

### Events

- `GET /api/events`: Liste aller Kurse/Events.
- `GET /api/events/{id}`: Details zu einem bestimmten Event.

### Reservierungen

- `GET /api/reservations`: Liste aller Reservierungen.
- `GET /api/reservations/{id}`: Details inkl. aller gebuchten Items.
- `POST /api/reservations`: Neue Reservierung erstellen.
    - Erwartet JSON mit `member_id`, optional `reservedFor`, `asset_type` und einer Liste von `items`.

### Leihausrüstung

- `GET /api/equipment`: Liste der allgemeinen Ausrüstung.
- `GET /api/equipment/{id}`: Details zu einem Ausrüstungsgegenstand.
- `GET /api/tanks`: Liste der Tauchflaschen.
- `GET /api/tanks/{id}`: Details zur Flasche.
- `GET /api/regulators`: Liste der Atemregler.
- `GET /api/regulators/{id}`: Details zum Atemregler.

### Diveclub Verwaltung

- `GET /api/students`: Liste der registrierten Schüler.
- `GET /api/students/{id}`: Schülerdetails.
- `GET /api/tank-checks`: Liste der TÜV-Prüfvorschläge.
- `GET /api/tank-checks/{id}`: Details zum Prüfvorschlag inkl. verknüpfter Artikel.

## Installation

1. Das Bundle via Composer hinzufügen:
   ```bash
   composer require diversworld/contao-dw-api-bundle
   ```
2. Contao Installtool ausführen oder Migrationen starten:
   ```bash
   vendor/bin/contao-console contao:migrate
   ```
3. Sicherstellen, dass das `diversworld/contao-diveclub-bundle` ebenfalls installiert ist, da dieses API-Bundle darauf
   aufbaut.

## Anforderungen

- Contao 5.x
- PHP 8.1 oder höher
- `diversworld/contao-diveclub-bundle`
