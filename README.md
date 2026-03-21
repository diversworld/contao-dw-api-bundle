[![Latest Version on Packagist](http://img.shields.io/packagist/v/diversworld/contao-dw-api-bundle.svg?style=flat)](https://packagist.org/packages/diversworld/contao-dw-api-bundle)
![Dynamic JSON Badge](https://img.shields.io/badge/dynamic/json?url=https%3A%2F%2Fraw.githubusercontent.com%2Fdiversworld%2Fcontao-dw-api-bundle%2Fmain%2Fcomposer.json&query=%24.require%5B%22contao%2Fcore-bundle%22%5D&label=Contao%20Version)
[![Installations via composer per month](http://img.shields.io/packagist/dm/diversworld/contao-dw-api-bundle.svg?style=flat)](https://packagist.org/packages/diversworld/contao-dw-api-bundle)
[![Installations via composer total](http://img.shields.io/packagist/dt/diversworld/contao-dw-api-bundle.svg?style=flat)](https://packagist.org/packages/diversworld/contao-dw-api-bundle)
![Packagist License](https://img.shields.io/packagist/l/diversworld/contao-dw-api-bundle)

![Diversworld](docs/dw-logo-k.png "Diversworld Logo")


iOS App https://apps.apple.com/de/app/diveclub-manager/id6759004094

# Contao Diveclub API Bundle

API Bundle für die Kommunikation zwischen Contao und einer iOS App im Rahmen des Diveclub Managers.

## Features

- **Events:** Abfrage von Tauchkursen und Terminen.
- **Reservierungen:** Verwaltung von Buchungen (Ansehen und Erstellen).
- **Ausrüstung:** Zugriff auf Leihausrüstung (Jackets, Anzüge, etc.).
- **Tauchflaschen & Atemregler:** Spezielle Endpunkte für Flaschen und Regler.
- **TÜV-Checks:** Übersicht über anstehende Revisionen und Prüfvorschläge.
- **App-Konfiguration & News:** Bereitstellung von Logo, Info-Texten und Nachrichten für die iOS-App.
- **Schüler:** Verwaltung von Kursteilnehmern.
- **Mitglieder:** Abruf der Mitgliederliste (ID, Nachname, Vorname, E-Mail).
- **JSON-Format:** Alle Antworten sind für die einfache Integration in iOS optimiert.

### JSON-Format & Datentypen

- **Datumswerte:** Alle Datumsfelder (z. B. `tstamp`, `reserved_at`, `picked_up_at`, `returned_at`, `lastOrder`,
  `buyDate`) werden einheitlich als **Integer-Timestamp** zurückgegeben.
- **Preise:** Preis- und Gebührenfelder (z. B. `rentalFee`, `totalPrice`, `price`) werden explizit als **Float**
  zurückgegeben.
- **Modelle:** Die Endpunkte geben grundsätzlich alle Felder der zugrundeliegenden Datenbank-Tabellen zurück (
  `model->row()`).

## API Endpunkte

Alle Endpunkte befinden sich unter dem Präfix `/api`.

### Events

- `GET /api/events`: Liste aller Kurse/Events.
- `GET /api/events/{id}`: Details zu einem bestimmten Event.

### Reservierungen

- `GET /api/reservations`: Liste aller Reservierungen des angemeldeten Benutzers.
- `GET /api/reservations/{id}`: Details inkl. aller gebuchten Items.
  - Gibt bei Items auch die Timestamps `created_at` und `updated_at` zurück.
- `POST /api/reservations`: Neue Reservierung erstellen.
  - Erwartet JSON mit `member_id`, optional `reservedFor`, `asset_type` (z.B. `equipment`, `tank`, `regulator`,
    `multiple`) und einer Liste von `items`.
  - Bei `items` können zusätzlich `item_id`, `item_type`, `types`, `sub_type` und `notes` übergeben werden.
  - **Beispiel Request:**
    ```json
    {
      "member_id": 1,
      "reservedFor": 1,
      "asset_type": "multiple",
      "items": [
        {
          "item_id": 42,
          "item_type": "equipment",
          "types": "jacket",
          "sub_type": "m",
          "notes": "Größe M bitte"
        },
        {
          "item_id": 12,
          "item_type": "tank",
          "notes": "12L Stahl"
        }
      ]
    }
    ```

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
  - Enthält nun zusätzliche Felder: `types`, `sub_type`, `type_label`, `sub_type_label`, `manufacturer_label`,
    `size_label` und `status_label`.
- `GET /api/equipment/{id}`: Details zu einem Ausrüstungsgegenstand.
  - Enthält nun zusätzliche Felder: `types`, `sub_type`, `type_label`, `sub_type_label`, `manufacturer_label`,
    `size_label` und `status_label`.
- `GET /api/equipment/options`: Liste aller verfügbaren Optionen für Ausrüstung (Typen, Hersteller, Größen).
  - Gibt ein JSON-Objekt mit den Schlüsseln `types`, `manufacturers` und `sizes` zurück.

### Tauchflaschen (Tanks)

- `GET /api/tanks`: Liste der verfügbaren Tauchflaschen.
  - **Sichtbarkeit:** Administratoren sehen alle Flaschen. Angemeldete Nutzer sehen ihre eigenen Flaschen (Status
    `owned`) sowie alle veröffentlichten Flaschen mit dem Status `available`. Gäste sehen alle veröffentlichten
    Flaschen.
  - **Filterung:** Unterstützt den Query-Parameter `status`.
    - `status=available`: Liefert alle veröffentlichten Flaschen mit dem Status `available` (z. B. für die
      Equipment-Auswahl).
    - `status=owned`: Liefert alle Flaschen mit dem Status `owned`, die dem aktuell angemeldeten Mitglied gehören (z. B.
      für TÜV-Reservierungen). Erfordert Authentifizierung.
- `GET /api/tanks/{id}`: Details zu einer bestimmten Flasche.
  - **Sicherheit:** Nur für Administratoren, den Besitzer oder bei öffentlicher Markierung zugänglich.
- `POST /api/tanks`: Neue Flasche anlegen.
  - **Pflichtfelder:** `title`, `serialNumber`, `size`.
  - **Besonderheit:** Der angemeldete Nutzer wird automatisch als Besitzer (`owner`) gesetzt, falls nicht anders
    angegeben.
- `PUT/PATCH /api/tanks/{id}`: Bestehende Flasche aktualisieren.
  - **Sicherheit:** Nur für Administratoren oder den Besitzer der Flasche erlaubt.
- `DELETE /api/tanks/{id}`: Flasche löschen.
  - **Sicherheit:** Nur für Administratoren oder den Besitzer der Flasche erlaubt.
- `GET /api/tanks/options`: Liste aller verfügbaren Optionen für Tauchflaschen (Größen, Hersteller).

#### Verfügbare Felder beim Speichern (Tanks):

Folgende Felder können via `POST` oder `PUT/PATCH` übertragen werden:
`title`, `alias`, `status`, `rentalFee`, `serialNumber`, `manufacturer`, `bazNumber`, `size`, `o2clean`, `owner`,
`checkId`, `lastCheckDate`, `nextCheckDate`, `lastOrder`, `addNotes`, `notes`, `published`, `start`, `stop`.

### Atemregler
- `GET /api/regulators/{id}`: Details zum Atemregler.
- `GET /api/regulator/options`: Liste aller verfügbaren Optionen für Atemregler (Hersteller, Modelle 1. & 2. Stufe).

### TÜV Prüfungen

- `GET /api/students`: Liste der registrierten Schüler.
- `GET /api/students/{id}`: Schülerdetails.
- `GET /api/tank-checks`: Liste der TÜV-Prüfvorschläge.
- `GET /api/tank-checks/{id}`: Details zum Prüfvorschlag inkl. verknüpfter Artikel.
- `POST /api/tank-checks/book`: Buchung einer TÜV-Prüfung für eine oder mehrere Flaschen.
    - Erwartet JSON mit `proposal_id` und einer Liste von `items` (Flaschendaten und gewählte Artikel-IDs).
  - Optional unterstützt: `notes` auf Buchungsebene sowie `notes` je Item/Flasche.
  - **Rückgabe:** Enthält den `total_price` der Buchung sowie eine Liste der `items` mit der berechneten `totalPrice`
    pro Flasche.
  - Beispiel Request:
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
          "price": 25.00,
          "o2clean": true,
          "articles": [1, 3, 5],
          "notes": "Ventil klemmt gelegentlich"
        }
      ]
    }
    ```
  - Beispiel Response:
    ```json
    {
      "success": true,
      "booking_number": "B-20260213-140700-123",
      "total_price": 45.50,
      "items": [
        {
          "serialNumber": "ABC123",
          "totalPrice": 45.50
        }
      ]
    }
    ```

### Authentifizierung

- `POST /api/login`: Login für Frontend-Benutzer.
    - Erwartet JSON mit `username` und `password`.
  - Gibt bei Erfolg Benutzerdaten inkl. `role` zurück.
  - Prüft, ob die API in der `tl_dc_config` aktiviert wurde.
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

### App & News

- `GET /api/app/config`: Liefert die globale App-Konfiguration (API-Status, Logo-Pfad, Info-Text, Impressum,
  Datenschutz, Nutzungsbedingungen, News-Archiv-ID).
- `GET /api/app/news`: Liste der News aus dem konfigurierten Archiv (inkl. Headline, Teaser und Vorschaubild).
  - Unterstützt Query-Parameter: `archive=[1,2]` (Liste von Archiv-IDs) und `limit=4` (Anzahl der Einträge).
- `GET /api/app/news/{id}`: Details zu einer News-Meldung.
  - Enthält nun zusätzlich ein `images` Array mit Pfaden zu allen Bildern aus den Inhaltselementen.
- `GET /api/app/news/details?id={id}`: Details zu einer News-Meldung via Query-Parameter.

### Mitglieder

- `GET /api/members`: Liste aller Mitglieder.
  - Antwortfelder: `mitglieds_id`, `name` (Nachname), `vorname`, `email`.
  - Gibt ein leeres Array zurück, wenn die API in der Diveclub-Konfiguration nicht aktiviert ist (`activateApi = true`
    erforderlich).
- Beispiel-Response:
  ```json
  [
    {"mitglieds_id": 12, "name": "Muster", "vorname": "Max", "email": "max.muster@example.org"},
    {"mitglieds_id": 13, "name": "Beispiel", "vorname": "Erika", "email": "erika.beispiel@example.org"}
  ]
  ```

## Konfiguration

Die Einstellungen für die App und die API werden im Contao Backend unter **Diveclub > Einstellungen (tl_dc_config)**
vorgenommen:

- **API aktivieren:** Schaltet den Zugriff für die App frei.
- **App-Logo:** Bilddatei für die Startseite.
- **Info-Text App:** Begrüßungstext für die Startseite.
- **Impressum (App):** Rechtliche Angaben für die App.
- **Datenschutzhinweise (App):** Hinweise zum Datenschutz für die App.
- **Nutzungsbedingungen (App):** Nutzungsbedingungen/AGB für die App.
- **News-Archiv:** Auswahl des Archivs, das in der App angezeigt werden soll.

## Installation

1. Das Bundle via Composer hinzufügen:
   ```bash
   composer require diversworld/contao-dw-api-bundle
   ```
2. Contao Installtool ausführen oder Migrationen starten (inkl. neuer Felder):
   ```bash
   ddev php vendor/bin/contao-console contao:migrate -n
   ddev php vendor/bin/contao-console cache:clear --env=prod
   ```
3. Sicherstellen, dass das `diversworld/contao-diveclub-bundle` ebenfalls installiert ist, da dieses API-Bundle darauf
   aufbaut.

## Anforderungen

- Contao 5.3 oder höher (optimiert für Contao 5.7)
- PHP 8.2 oder höher
- `diversworld/contao-diveclub-bundle`

## Kompatibilität & Modernisierung

Das Bundle wurde für **Contao 5.7** und **Symfony 7** optimiert:

- **Routing:** Verwendung von PHP 8 Attributen (`#[Route]`) statt Annotationen. Durch die explizite Angabe des Typs
  `attribute` im `ContaoManager` Plugin wird die Kompatibilität mit Contao 5.3+ sichergestellt.
- **Security:** Nutzung des `UserPasswordHasherInterface` und `IsGranted` Attributen.
- **String Handling:** Umstellung auf `StringUtil::restoreBasicEntities()` für volle Kompatibilität mit Contao 5+.
