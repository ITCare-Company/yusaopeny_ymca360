CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* How it works
* Drush commands
* Maintainers

INTRODUCTION
------------

YUSA OpenY YMCA360 Integration module pulls YMCA360 program and live stream
schedules into the Open Y Program Event Framework.

This fork (ITCare-Company/yusaopeny_ymca360) extends the upstream module with
a windowed, reconciliation-based syncer:

* Fetches only the next N days from the API (configurable, default 14) using
  the native `start_at` / `end_at` / `scheduled_from` filters — no
  unbounded paging, no in-memory cap.
* Treats the API as the source of truth: every full sync removes mappings
  that are no longer in the extract (orphans + status=deleted), capped per
  run as a safety guard.
* Plays well with the Trash module: the syncer wraps deletes in
  `trash.manager->executeInTrashContext('ignore', ...)` so reconciliation
  performs a real delete instead of soft-trashing.
* Configurable canceled UX (ITCR-1239): canceled occurrences stay
  published with a "CANCELED:" prefix by default so members see the
  cancellation on the schedule page just like in the Y360 app.
* Optional fields `field_session_status` and
  `field_session_original_instructor` are written to session nodes when
  the bundle exposes them, letting themes apply strikethrough or
  substitute-instructor labels.

REQUIREMENTS
------------

* Open Y 11.0+ (Drupal 10/11).
* Trash module (recommended). The syncer detects it at runtime; without
  it deletes go through the standard storage path.

INSTALLATION
------------

Install via composer (path repo or git VCS) and enable two modules:

* YMCA360 Integration (`yusaopeny_ymca360`)
* YMCA360 Integration - Schedules sync (`yusaopeny_ymca360_instudio`)

The submodule's install hook adds `yusaopeny_ymca360_instudio.syncer` to
`ymca_sync.settings.active_syncers` automatically.

`drush updb` after upgrade is required:

* `update_10001` backfills the new sync settings (window_days, page_size,
  max_deletes_per_run, canceled_title_prefix, canceled_publish_behavior)
  on existing installs.
* `update_10002` excludes syncer-owned bundles (session, activity, class,
  program, program_subcategory) from `trash.settings.enabled_entity_types`
  so reconciliation deletes are real.

CONFIGURATION
-------------

`Administration » YMCA Website Services » Integrations » YMCA360`:

* **Credentials & Schedules** — credentials and the list of `schedule_id`
  values to pull (one Y has multiple calendars: Group Fitness, Pool,
  Racquetball, etc.).
* **Locations mapping** — maps Y360 branches/studios onto site locations.

`Administration » YMCA Website Services » Integrations » YMCA360 In-Studio`:

* **Automatic Sync** — enable_cron toggle. When on, the module's
  `hook_cron` runs the syncer.
* **Sync Window** —
  * `window_days` (default 14) — how many days ahead of "now" to pull.
    Items before now are never extracted, so reconciliation removes
    them on the next run.
  * `page_size` (default 500) — items per API page.
  * `max_deletes_per_run` (default 500) — hard cap on deletions per
    sync cycle. Excess is logged and deferred to subsequent runs to
    protect against misconfigurations (e.g. a schedule_id toggled off).
* **Canceled sessions** —
  * `Title prefix` (default `CANCELED: `).
  * `Publish behavior`:
    * `keep_published` (default) — canceled stays visible with the
      prefix, matching Y360 app UX.
    * `follow_api` — respects the API `published` flag.
    * `always_unpublish` — hides canceled entirely.

HOW IT WORKS
------------

```
              ┌────────┐    ┌─────────────┐    ┌────────┐
   API   ───▶ │Extract │ ─▶ │  Transform  │ ─▶ │  Load  │ ─▶ Drupal
              └────────┘    └─────────────┘    └────────┘
```

* **Extractor**: builds `[now, now + window_days]` and calls
  `Y360Client::getSchedulesWindowed()` with native `start_at` /
  `end_at` / `scheduled_from` filters. Pagination is API-driven; no
  client-side cap.
* **Transformer**:
  * Items with `status=deleted` → existing mapping queued for delete.
  * Items with hash matching the existing mapping → no-op.
  * Items with hash differing → update.
  * Items without an existing mapping → create.
  * Mappings whose `y360id` is NOT in the current extract → orphan,
    queued for delete (capped by `max_deletes_per_run`).
* **Loader**:
  * Creates / updates session nodes via `applySessionFields()`. Fields:
    title (with optional cancel prefix), class/activity, time
    paragraph, location, room, instructor, description, ages,
    optional `field_wait_list_availability`,
    `field_session_original_instructor`, `field_session_status`.
  * Publish state derives from `isPublishedSession()` — for canceled,
    it consults `canceled_publish_behavior`.
  * Deletes are wrapped in
    `trash.manager->executeInTrashContext('ignore', ...)` so
    reconciliation removes the session node outright.

The hash on each `y360_mapping` row is `md5(serialize($apiItem))` so any
field change in the upstream item triggers an update on the next sync.

Underthehood the module uses the YMCA Sync module
(https://github.com/ymcatwincities/ymca_sync).

If the sync executes via Drupal cron, run cron often (e.g. every 15
minutes). Sites that prefer an explicit Jenkins job can wire one with
`drush y360:sync`.

DRUSH COMMANDS
--------------

* `drush y360:status` — shows current sync state: window bounds,
  mapping counts (in-window / past / future / blank-hash), last
  cron run, key config values.
* `drush y360:sync [--syncer=…]` — runs the syncer once. Defaults to
  `yusaopeny_ymca360_instudio.syncer`.
* `drush y360:reset-hashes` — clears `hash` on every mapping row.
  Use after changing settings that affect rendered fields
  (`canceled_publish_behavior`, `canceled_title_prefix`, etc.) so the
  next sync rewrites session nodes instead of skipping them via the
  hash no-op path.
* `drush y360:cleanup [--limit=N]` — runs the legacy past-sessions
  cleaner (default limit 50). Window-based reconciliation already
  removes past mappings on every full sync, so this is a manual
  fallback.
* `drush y360:ping` — pings the YMCA360 API and prints a one-line OK
  / failure message; useful for credential checks.

MAINTAINERS
-----------

Current maintainers:

* ITCare-Company — fork at https://github.com/ITCare-Company/yusaopeny_ymca360
* andreymaximov - https://www.drupal.org/u/andreymaximov
* Five Jars - https://fivejars.com/
