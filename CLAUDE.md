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
  'ghlTags' => 'website-form,bricks-form',
  'ghlSource' => 'Website Bricks Form',
  'ghlFailBehavior' => 'block',
]
```

## Notes

- Do not log API tokens or full request headers.
- Validation should require at least one mapped `email` or `phone` field.
- The plugin should fail gracefully if Bricks is inactive.
