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
- Source override
- Additional comma-separated tags
- Error behavior

Per-form settings are stored in the Bricks Form element settings so they move with Bricks templates and page exports.
