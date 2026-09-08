# Changelog

All notable changes to this plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.1] - 2026-09-08

### Added

- `CHANGELOG.md` and a versioning rule in `CLAUDE.md`, so every functional change
  bumps the plugin version and gets a changelog entry.

## [0.3.0] - 2026-09-08

### Added

- Client side conversion tracking for Google Tag Manager. `assets/js/tracking.js`
  listens for the Bricks `bricks/form/success` event and pushes a single
  `dataLayer` event `{ event, form_id }` for forms that use the GHL action.
- Global settings `dataLayer tracking` (on/off) and `dataLayer event name`
  (default `ghl_lead`).
- Per-form Bricks control `Tracking ID (GTM)`, pushed as `form_id` and falling
  back to the Bricks element ID when empty.
- `Bricks\TrackingScript`, which marks GHL enabled form roots with
  `data-bghl-form` and `data-bghl-tracking-id` and enqueues the listener.
- `BGHL_CONNECTOR_URL` constant for asset URLs.
- README section documenting the `dataLayer` contract and the GTM container setup.

### Changed

- Settings page copy no longer claims the event waits for a GHL confirmation, and
  debug logging no longer mentions the browser console.

## [0.2.1] - 2026-07-27

### Fixed

- GHL control group condition in the Bricks form element, so the group shows only
  when the GHL action is selected.

## [0.2.0] - 2026-07-23

### Added

- API token and Location ID can be defined in `wp-config.php`
  (`BRICKS_GHL_CONNECTOR_API_TOKEN`, `BRICKS_GHL_CONNECTOR_LOCATION_ID`), which
  overrides the values saved in the database.

### Changed

- GHL API messages are returned to the form and are translatable.
- Corrections to the per-form GHL mapping controls.

## [0.1.0] - 2026-07-23

### Added

- Initial plugin: GoHighLevel action for Bricks Builder forms, site level
  settings page, field mapping to GHL standard and custom fields, default source
  and tags, error behavior and debug logging.

[0.3.1]: https://github.com/dokker/bricks-ghl-connector/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/dokker/bricks-ghl-connector/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/dokker/bricks-ghl-connector/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/dokker/bricks-ghl-connector/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/dokker/bricks-ghl-connector/releases/tag/v0.1.0
