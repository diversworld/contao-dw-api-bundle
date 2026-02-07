![Alt text](docs/logo.png?raw=true "logo")

# Contao DW API Bundle

API Bundle für die Kommunikation zwischen Contao und einer iOS App.

## Features

- API Endpunkte für den Diveclub Manager:
    - **Events:** `/api/events`
    - **Reservierungen:** `/api/reservations` (GET, POST für neue Buchungen)
    - **Equipment:** `/api/equipment`
    - **Tauchflaschen:** `/api/tanks`
    - **Atemregler:** `/api/regulators`
    - **TÜV-Termine:** `/api/tank-checks`
    - **Schüler:** `/api/students`
- JSON Antworten für iOS Integration

## Installation

1. Das Bundle via Composer hinzufügen (lokal oder Repository).
2. Contao Installtool ausführen.
3. Die API ist unter `/api/reservations` und `/api/events` erreichbar.
