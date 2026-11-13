# Editorial Reminder Dashboard Plugin for OJS 3.3

**Copyright (c) 2026 Indaka Barody**  
Distributed under the GNU GPL v3. For full terms see the file `LICENSE`.

## Overview

**Editorial Reminder Dashboard** (`editorialReminderDashboard`) is a generic plugin for **Open Journal Systems (OJS) 3.3.x** that monitors active submissions in the **Review** (Round 1 and Round 2+) and **Copyediting** workflow stages based on working-day rules and target issue publication dates (`H`).

## Key Features

- **Working-Day Calculation Engine**:
  - Configurable active working days of the week via checkboxes (Monday through Sunday, defaulting to Monday through Friday), excluding non-selected days and dates configured in the National Holidays & Collective Leave table (`editorial_reminder_holidays`).
  - Supports configurable timezone per journal or server default (`Asia/Jakarta`).
- **Review Stage Monitoring**:
  - Configurable Round 1 limit (14–21 working days, default: 21 working days) and Round 2+ limit (default: 7 working days).
  - Configurable almost-due warning thresholds (default: 3 working days for Round 1, 2 working days for Round 2+).
  - Automatically determines round start date from editor decisions (`Send to Review` / `New Review Round`) with fallback to earliest reviewer assignment date.
  - Pauses round timer when waiting for author revisions after a `Request Revisions` decision.
  - Stores completed review rounds in `editorial_reminder_round_history` and calculates cumulative working days across all rounds.
  - Displays detailed reviewer statuses, OJS native overdue indicators, and automatic delay cause diagnosis (waiting for reviewers, waiting for editor decision, or no reviewers assigned).
- **Copyediting & Target Issue Monitoring**:
  - Allows Site Administrators and Journal Managers to assign a **Target Issue** (Future Issue or Back Issue) to each Copyediting article.
  - Tracks Target Publication Date (`H`) per issue, defaulting to the last day of the issue's month.
  - Evaluates calendar-day reminders (`Normal` > H-10, `Almost due` H-10..H-1, `Due today` H+0, `Overdue` past H+n, and `No target issue`).
  - Records full audit logs of Target Issue changes.
- **Per-Journal & Site-Level (Publishing House) Dashboards**:
  - **Journal Dashboard**: Accessible exclusively to **Site Administrators** and **Journal Managers** (hidden from Journal Editors, Production Editors, and Section Editors). Provides summary cards, stage tabs (`All`, `Review`, `Copyediting`), search, filters (`Status`, `Editor`, `Issue`, `Sort`), expandable reviewer and round history panels, and direct links to OJS editorial workflow.
  - **Publishing House Dashboard (Site-Level)**: Aggregated totals across all hosted journals, per-journal status distribution bar, and most urgent articles across journals for **Site Administrators**.
- **Multi-Format Exports**:
  - Exports active filtered views to native **Excel (`.xlsx`)** (multi-sheet with Sheet 1: Articles/Recap + Metadata Header, Sheet 2: Reviewer Details), **CSV (`.csv`)** (UTF-8 with BOM), and **PDF (`.pdf`)** (Landscape A4 report).
- **Scheduled Email Digests**:
  - Daily digest to Editors/Section Editors and weekly digest to Journal Managers via OJS `AcronPlugin` (`scheduledTasks.xml`), with per-user notification opt-out.

## Installation

1. Copy or extract this plugin folder to `plugins/generic/editorialReminderDashboard` in your OJS 3.3 installation.
2. Run database migration (optional — the plugin also self-initializes its custom tables automatically when enabled):
   ```bash
   php tools/upgrade.php upgrade
   ```
3. Log in to OJS as Journal Manager or Site Administrator, navigate to **Settings > Website > Plugins**, and enable **Editorial Reminder Dashboard**.
4. Open **Editorial Reminder** from the left backend navigation sidebar.
