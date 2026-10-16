# Bricks GHL Connector

WordPress plugin that adds a GoHighLevel action to Bricks Builder forms.

## Requirements

- WordPress
- Bricks Builder theme
- PHP 7.4 or newer
- GoHighLevel sub-account API key or private integration token
- GoHighLevel Location ID

## Global Configuration

The plugin uses one site-level GHL credential set per WordPress installation.

You can configure it in the WordPress admin:

1. Go to `Settings > Bricks GHL Connector`.
2. Enter the `GHL API token`.
3. Enter the `Location ID`.
4. Set default source, default tags, and optional debug logging.
5. Optionally set `Phone number format` and `Full name order` (see
   [Field normalization](#field-normalization)). Both are off by default, so
   values are sent as submitted.

## wp-config.php Configuration

For production sites, define the API token in `wp-config.php` so it is not stored in the WordPress database:

```php
define('BRICKS_GHL_CONNECTOR_API_TOKEN', 'your-ghl-sub-account-api-key');
```

The Location ID can also be defined in `wp-config.php`:

```php
define('BRICKS_GHL_CONNECTOR_LOCATION_ID', 'your-ghl-location-id');
```

When these constants are defined, they override the values saved on the plugin settings page.

## Bricks Form Setup

1. Edit a Bricks Form element.
2. In `Actions after successful form submit`, select `GoHighLevel`.
3. Open the `GHL` control group.
4. Add field mappings from Bricks fields to GHL standard fields, custom field IDs, or the contact note.
5. Save the page or template.

At least one field must be mapped to `email` or `phone`.

### Repeat submissions and messages

Contacts are sent to the GHL `POST /contacts/upsert` endpoint, so a visitor can
submit the same form again with the same email or phone. GHL then updates the
existing contact, following the location's "Allow Duplicate Contact" setting,
instead of rejecting the submission.

Map the message (textarea) field to the `Contact note (message)` target type.
Every submission is added as a new note on the contact, headed with the source and
labelled with the Bricks field label, so earlier messages are never overwritten.
Several fields can be mapped to the note; they end up in the same note.

| Question | Answer |
| --- | --- |
| Bricks field type | `Textarea` (any text field works, a textarea suits long messages) |
| Mapping target type | `Contact note (message)` |
| Where it shows in GHL | `Contacts > open the contact > Notes` tab, one note per submission |

A note looks like this:

```text
Website form submission: Website Bricks Form

Message:
Hello, I would like an offer.
```

Do not map the message to a GHL custom field unless you only need the latest
message: a custom field holds a single value, so the next submission from the same
contact overwrites it. Notes keep the full history.

Tags are added with a separate `POST /contacts/{id}/tags` request, because upsert
would replace the tags the contact already has. A failed tag request is only
logged. A failed note request follows the form's error behavior setting.

## Field normalization

### Date of birth

Map a field to the `Date of birth` standard field (`dateOfBirth` in GHL). The
Bricks `Date` field already sends `2005-03-14`. A date typed into a text field is
accepted in these forms and sent as `Y-m-d`:

`2005-03-14`, `2005.03.14`, `2005.03.14.`, `2005. 03. 14.`, `2005/03/14`,
`14.03.2005`, `14/03/2005`

A value that cannot be parsed, or an impossible date such as `2005-02-30`, is
left out of the request and logged as a warning, because GHL would reject the
whole contact for an invalid date. The submission still goes through.

### Phone number format

`Settings > Bricks GHL Connector > Phone number format` (option key
`phone_default_country`, default empty = send as submitted).

With `Hungary (+36)`, national formats are converted to E.164, so GHL matches
repeat submissions to the same contact by phone:

| Submitted | Sent |
| --- | --- |
| `06 30 123 4567` | `+36301234567` |
| `+36-30-123-4567` | `+36301234567` |
| `0036301234567` | `+36301234567` |
| `301234567` | `+36301234567` |
| `36 30 123 4567` | `+36301234567` |
| `(06-1) 234-5678` | `+3612345678` |
| `+44 20 7946 0958` | `+442079460958` (foreign numbers are kept) |

Spaces, hyphens, parentheses and slashes are removed. The part after `+36` must
be 9 digits after a mobile prefix (`20`, `30`, `31`, `50`, `70`) or `21`, and 8
digits after any other area code, so a mobile number typed one digit short
(`06 30 123 456`) is not accepted. A number that is not valid is sent unchanged
and logged as a warning, because the phone may be the only contact identifier.
The log does not contain the number.

### Full name order

`Settings > Bricks GHL Connector > Full name order` (option key `name_order`,
`given_first` by default).

Only used when a form maps `Full name` but neither `First name` nor `Last name`.
With `Family name first` the last word becomes the first name and everything
before it the last name, so the GHL greeting uses the given name:

| Full name | First name | Last name |
| --- | --- | --- |
| `Nagy Dénes` | `Dénes` | `Nagy` |
| `Kovácsné Szabó Anna` | `Anna` | `Kovácsné Szabó` |
| `Péter` | | `Péter` |

The full name is sent as well. `Given name first` sends only the full name and
leaves the splitting to GHL, as before.

## Developer hooks

### `bghl_connector/after_submit`

Fires once per submission of a form that uses the GHL action, on every outcome.
Use it to store the GHL status and contact ID next to your own copy of the
submission.

| Key | Type | Value |
| --- | --- | --- |
| `status` | `string` | `success` (the contact was upserted), `failed` (configuration error or the upsert failed), `skipped` (the visitor submitted no email, phone or name) |
| `contact_id` | `?string` | GHL contact ID, set when `status` is `success` |
| `is_new` | `?bool` | Whether GHL created a new contact |
| `failed_step` | `?string` | `validation`, `upsert`, `tags` or `note`; `null` when everything worked |
| `error` | `?string` | Error message for `failed_step`, never contains the API token |
| `tracking_id` | `string` | Per-form `Tracking ID (GTM)`, or the Bricks element ID |
| `submitted_at` | `string` | ISO 8601 time in UTC, e.g. `2026-10-16T12:00:00+00:00` |

A failed tag or note request keeps `status` at `success`, because the contact
exists; `failed_step` tells which step failed. If both fail, `failed_step` is
`note`.

```php
add_action('bghl_connector/after_submit', static function (array $result, $form): void {
    $submissionId = method_exists($form, 'get_submission_id') ? $form->get_submission_id() : null;

    my_submissions_store_ghl_result($submissionId, [
        'ghl_status' => $result['status'],
        'ghl_contact_id' => $result['contact_id'],
        'ghl_failed_step' => $result['failed_step'],
        'ghl_error' => $result['error'],
    ]);
}, 10, 2);
```

### `bghl_connector/contact_payload`

Filters the upsert payload right before it is sent. A `tags` key is removed
after the filter, because upsert would replace the contact's existing tags.

```php
add_filter('bghl_connector/contact_payload', static function (array $payload, $form): array {
    $payload['companyName'] = $payload['companyName'] ?? 'Private person';

    return $payload;
}, 10, 2);
```

### Smoke check

`bin/smoke.php` checks the normalization and the payload builder offline, without
calling GHL. It is not part of the release ZIP.

```bash
wp eval-file wp-content/plugins/bricks-ghl-connector/bin/smoke.php
```

## Per-Form Settings

Each Bricks form can define:

- Field mappings
- Tracking ID used for conversion tracking
- Source override
- Additional comma-separated tags
- Error behavior

Per-form settings are stored in the Bricks Form element settings so they move with Bricks templates and page exports.

## Conversion Tracking (Google Tag Manager)

Bricks forms submit over AJAX, so there is no thank you page to trigger a
conversion on. The plugin listens for the Bricks `bricks/form/success` event and
pushes one `dataLayer` event for forms that use the GHL action.

Enable it under `Settings > Bricks GHL Connector > dataLayer tracking`.

### dataLayer contract

```js
{
  event: 'ghl_lead',      // configurable, one stable name for every form
  form_id: 'contact-form' // per-form "Tracking ID (GTM)" control,
                          // falls back to the Bricks element ID
}
```

Set the tracking ID per form in the builder: select the Form element, open the
**GHL** control group and fill in **Tracking ID (GTM)**. That value is what you
match on in the container, so you never have to look up the Bricks element ID in
the page source.

### Google Tag Manager setup

1. Create a **Custom Event** trigger with the event name `ghl_lead`.
2. Create a **Data Layer Variable** for `form_id`.
3. Point the GA4 event / Ads conversion tag at that trigger.
4. To separate forms, keep the single trigger and add a condition on `form_id`.
   Do not create a different event name per form.

No custom HTML tag is needed in the container.

### Notes

- No email or phone number is pushed to the `dataLayer`.
- A `dataLayer.push()` is not a cookie or a tag, so it can run before consent.
  Consent conditions belong on the GTM tags (Consent Mode v2).
- With the default `On GHL error > Show form error`, a failed GHL call blocks the
  form success event, so no conversion is counted. With `Log only` the form
  reports success regardless, and the event fires even if GHL rejected the
  contact.
- The event is client side only. It fires when Bricks reports a successful
  submission, not when the GHL API confirmed the contact.

## Updates

From version 0.5.0 on, new releases show up in the WordPress admin under
`Dashboard > Updates` and on the Plugins screen, and install with the normal
update button. The plugin checks the `info.json` asset of the latest GitHub
release every 12 hours; `Dashboard > Updates > Check again` checks immediately.

The plugin must be installed in the `wp-content/plugins/bricks-ghl-connector/`
folder, which is where the release ZIP installs it. Versions before 0.5.0 have to
be updated to 0.5.0 by hand once.

### Releasing

1. Bump `Version:` and `BGHL_CONNECTOR_VERSION` in `bricks-ghl-connector.php` and
   add the `CHANGELOG.md` entry.
2. Push a `vX.Y.Z` tag. The release workflow checks that the tag, both versions
   and the changelog agree, then uploads `bricks-ghl-connector.zip` and
   `info.json` to the GitHub release.

Drafts and pre-releases are not offered as updates.

### Manual download

Download the latest release from this URL:
https://github.com/dokker/bricks-ghl-connector/releases/latest/download/bricks-ghl-connector.zip
