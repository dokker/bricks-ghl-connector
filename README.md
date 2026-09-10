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
4. Add field mappings from Bricks fields to GHL standard fields or custom field IDs.
5. Save the page or template.

At least one field must be mapped to `email` or `phone`.

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

Download the latest release from this URL:
https://github.com/dokker/bricks-ghl-connector/releases/latest/download/bricks-ghl-connector.zip
