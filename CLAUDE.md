# Bricks GHL Connector

## Project Decisions

- This is a WordPress plugin add-on for Bricks Builder.
- The plugin adds a `GoHighLevel` action to Bricks Form elements.
- MVP uses one site-level GoHighLevel sub-account API key / token per WordPress install.
- MVP stores global credentials in a plugin-owned WordPress settings page, not in Bricks API keys, because this is stable and portable across Bricks versions.
- Per-form settings are stored inside the Bricks Form element settings so pages/templates carry their mapping during export/import.
- Code, comments, settings keys, and internal UI labels are English.
- The initial transport is the GHL Contacts API. Webhook delivery is a future extension.
- Every submitted contact should receive a tag indicating it came from a website form.

## Architecture

- `Admin`: site-level settings, settings repository, option sanitization.
- `Bricks`: Bricks form action registration, control group registration, submit hook handling.
- `Mapping`: Bricks submitted field IDs to GHL standard fields and custom field IDs.
- `GHL`: HTTP client and contact payload builder.
- `Support`: logging and sanitization utilities.

## GHL Configuration

- Global setting: API token / sub-account API key.
- Global setting: Location ID.
- Global setting: default source.
- Global setting: default tags.
- Global setting: debug logging flag.
- Global setting: dataLayer tracking flag.
- Global setting: dataLayer event name.

## Per-Form Mapping Shape

```php
[
  'ghlFieldMappings' => [
    [
      'bricksFieldId' => 'form-field-id',
      'targetType' => 'standard',
      'standardField' => 'email',
      'customFieldId' => '',
    ],
    [
      'bricksFieldId' => 'another-form-field-id',
      'targetType' => 'custom',
      'standardField' => '',
      'customFieldId' => 'ghl_custom_field_id',
    ],
  ],
  'ghlTrackingId' => 'contact-form',
  'ghlTags' => 'website-form,bricks-form',
  'ghlSource' => 'Website Bricks Form',
  'ghlFailBehavior' => 'block',
]
```

## Notes

- Do not log API tokens or full request headers.
- Validation should require at least one mapped `email` or `phone` field.
- The plugin should fail gracefully if Bricks is inactive.
- Conversion tracking is client side: `assets/js/tracking.js` listens for `bricks/form/success` and pushes `{ event, form_id }`. Keep it that way unless offline conversion import or CAPI deduplication is actually needed, which is the only thing that would justify a server-built payload again.
- The per-form tracking ID is rendered onto the form root as `data-bghl-tracking-id`, so the browser never has to look at the form response.
- Bricks specific tracking code stays in `Bricks\TrackingScript` and `assets/js/tracking.js` so a different form builder only needs a new adapter.

## Versioning and Changelog (required on every change)

Every code change to this plugin must ship with a version bump and a changelog
entry in the same commit. This is not optional and does not need to be asked for.

1. Bump the version in **both** places in `bricks-ghl-connector.php`: the
   `Version:` plugin header and `define('BGHL_CONNECTOR_VERSION', ...)`. The two
   must always match, because the constant is the asset cache buster.
2. Add an entry to `CHANGELOG.md` at the top, under a new
   `## [x.y.z] - YYYY-MM-DD` heading, using Keep a Changelog sections
   (`Added`, `Changed`, `Fixed`, `Removed`). Also add the compare link at the
   bottom of the file.
3. Semantic versioning:
   - patch (`0.3.0` to `0.3.1`): bug fix, copy or docs change, refactor with no
     behavior change.
   - minor (`0.3.x` to `0.4.0`): new feature, new setting, new Bricks control,
     new hook.
   - major: breaking change, such as removing or renaming a stored setting key,
     a per-form setting, or the `dataLayer` contract.
4. If a stored option key or per-form setting shape changes, say so explicitly in
   the changelog entry, because existing sites carry that data.
5. Pure repository housekeeping that does not ship in the plugin ZIP
   (`.gitignore`, CI config) does not need a version bump, but still gets a
   changelog line if it changes how the plugin is built or released.
