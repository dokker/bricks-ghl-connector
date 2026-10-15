# Changelog

All notable changes to this plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.0] - 2026-10-15

### Added

- Updates from GitHub releases: new versions show up under `Dashboard > Updates`
  and on the Plugins screen, and install with the normal WordPress update button.
  `Updates\GitHubUpdater` reads `info.json` from the latest release (a plain file
  download, so the GitHub API rate limit does not apply) and caches it for 12
  hours, or 1 hour after a failed request. `Check again` bypasses the cache.
- `View details` shows the plugin description and the last five changelog entries.
- Plugin headers `Update URI` (so wordpress.org never offers an update for this
  slug) and `Requires at least: 5.8`.
- The release workflow builds `info.json` with `.github/scripts/build-info.py` and
  uploads it next to the ZIP. The build fails if the tag, the `Version` header,
  `BGHL_CONNECTOR_VERSION` and `CHANGELOG.md` disagree.

New site transient `bghl_connector_update_info`. No stored option or per-form
setting changes.

### Changed

- README: documents which Bricks field type and mapping target to use for the
  message, and that it appears in the contact's Notes tab in GHL (one note per
  submission). This was prepared as 0.4.2, which was never released.

## [0.4.1] - 2026-10-14

### Changed

- The release ZIP is built with `git archive`: every file sits under a
  `bricks-ghl-connector/` folder, and `.github/`, `.gitattributes` and `CLAUDE.md`
  are left out (see `.gitattributes`).
- Release workflow actions updated to their Node 24 versions
  (`actions/checkout@v7`, `softprops/action-gh-release@v3`).

## [0.4.0] - 2026-10-14

### Added

- New field mapping target type `Contact note (message)` (stored as
  `targetType` `note` in `ghlFieldMappings`). The mapped values are added as a new
  note on the contact on every submission, labelled with the Bricks field labels.
  Additive: existing `standard` and `custom` rows are unchanged.
- `GHL\NoteBuilder` (loaded from the main plugin file) and `Client::addTags()` / `Client::createNote()`.

### Changed

- Contacts are sent to `POST /contacts/upsert` instead of `POST /contacts/`, so the
  same email or phone can submit the form again. The existing contact is updated
  according to the location's "Allow Duplicate Contact" setting.
- Tags are added with `POST /contacts/{id}/tags` after the upsert instead of in the
  contact payload, so existing tags on the contact are kept.

### Removed

- The "This email address / phone number is already subscribed" form errors and
  the duplicate detection helpers in `ApiException`, since duplicates no longer fail.

## [0.3.2] - 2026-09-10

### Added

- `.github/workflows/release.yml` for creating clean ZIP file for the retlease.

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

[0.5.0]: https://github.com/dokker/bricks-ghl-connector/compare/v0.4.1...v0.5.0
[0.4.1]: https://github.com/dokker/bricks-ghl-connector/compare/v0.4.0...v0.4.1
[0.4.0]: https://github.com/dokker/bricks-ghl-connector/compare/v0.3.2...v0.4.0
[0.3.2]: https://github.com/dokker/bricks-ghl-connector/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/dokker/bricks-ghl-connector/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/dokker/bricks-ghl-connector/compare/v0.2.1...v0.3.0
[0.2.1]: https://github.com/dokker/bricks-ghl-connector/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/dokker/bricks-ghl-connector/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/dokker/bricks-ghl-connector/releases/tag/v0.1.0
