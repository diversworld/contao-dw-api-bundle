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

### Kurse

- `GET /api/courses`: Liste aller verfügbaren Kurse.
- `GET /api/courses/{id}`: Details zu einem bestimmten Kurs.
- `POST /api/courses/enroll`: Anmeldung zu einem Kurs.
    - Erwartet JSON mit `course_id` und optional `event_id`.

### Kursanmeldungen

- `GET /api/enrollments`: Liste der Kursanmeldungen des aktuell angemeldeten Benutzers.

### Instruktoren

- `PATCH /api/instructor/approve/{id}`: Kursanmeldung eines Schülers genehmigen (Status auf `active` setzen).
- `PATCH /api/instructor/reject/{id}`: Kursanmeldung eines Schülers ablehnen (Status auf `dropped` setzen).

### Kursfortschritt

- `GET /api/progress`: Kursfortschritt des aktuell angemeldeten Schülers.
- `GET /api/progress/instructor`: (Instruktoren) Liste der Schüler und deren Fortschritte für alle betreuten
  Kurse/Events.
- `PATCH /api/progress/{exerciseId}`: (Instruktoren) Übung als abgeschlossen markieren oder Notizen hinzufügen.
    - Erwartet JSON mit `status`, `dateCompleted` (Timestamp) und optional `notes`.

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
- `POST /api/tank-checks/book`: Buchung einer TÜV-Prüfung für eine oder mehrere Flaschen.
    - Erwartet JSON mit `proposal_id` und einer Liste von `items` (Flaschendaten und gewählte Artikel-IDs).
    - Optional unterstützt: `notes` auf Buchungsebene sowie `notes` je Item/Flasche.
    - Beispiel:
      ```json
      {
        "proposal_id": 12,
        "notes": "Bitte Rückruf bei Rückfragen.",
        "items": [
          {
            "serialNumber": "ABC123",
            "manufacturer": "Scubatech",
            "bazNumber": "B12345",
            "size": "12",
            "o2clean": true,
            "articles": [1, 3, 5],
            "notes": "Ventil klemmt gelegentlich"
          }
        ]
      }
      ```

### Authentifizierung

- `POST /api/login`: Login für Frontend-Benutzer.
    - Erwartet JSON mit `username` und `password`.
  - Gibt bei Erfolg Benutzerdaten inkl. `role` zurück.
- `POST /api/logout`: Logout für Frontend-Benutzer.
    - Beendet die aktuelle Session.
- `GET /api/me`: Aktuelle Benutzerdaten inkl. `role` abrufen.
    - Erfordert eine aktive Session.
- `PATCH /api/me`: Eigene Benutzerdaten aktualisieren.
    - Erwartet JSON mit den zu ändernden Feldern (z.B. `firstname`, `lastname`, `email`, etc.).
    - Erfordert eine aktive Session.
- `PATCH /api/password`: Passwort ändern.
    - Erwartet JSON mit `currentPassword` und `newPassword`.
    - Erfordert eine aktive Session.

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
