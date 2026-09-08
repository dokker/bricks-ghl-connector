<?php
/**
 * Plugin Name: Bricks GHL Connector
 * Description: Adds a GoHighLevel form action to Bricks Builder forms.
 * Version: 0.3.0
 * Author: Vertical
 * Text Domain: bricks-ghl-connector
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('BGHL_CONNECTOR_VERSION', '0.3.0');
define('BGHL_CONNECTOR_FILE', __FILE__);
define('BGHL_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('BGHL_CONNECTOR_URL', plugin_dir_url(__FILE__));

require_once BGHL_CONNECTOR_PATH . 'src/Support/Sanitizer.php';
require_once BGHL_CONNECTOR_PATH . 'src/Support/Logger.php';
require_once BGHL_CONNECTOR_PATH . 'src/Admin/SettingsRepository.php';
require_once BGHL_CONNECTOR_PATH . 'src/Admin/SettingsPage.php';
require_once BGHL_CONNECTOR_PATH . 'src/Mapping/StandardFields.php';
require_once BGHL_CONNECTOR_PATH . 'src/Mapping/MappingValidator.php';
require_once BGHL_CONNECTOR_PATH . 'src/Mapping/FieldMapper.php';
require_once BGHL_CONNECTOR_PATH . 'src/GHL/ApiException.php';
require_once BGHL_CONNECTOR_PATH . 'src/GHL/Client.php';
require_once BGHL_CONNECTOR_PATH . 'src/GHL/ContactPayloadBuilder.php';
require_once BGHL_CONNECTOR_PATH . 'src/Bricks/FormControls.php';
require_once BGHL_CONNECTOR_PATH . 'src/Bricks/FormSubmitHandler.php';
require_once BGHL_CONNECTOR_PATH . 'src/Bricks/TrackingScript.php';
require_once BGHL_CONNECTOR_PATH . 'src/Bricks/FormActionRegistrar.php';
require_once BGHL_CONNECTOR_PATH . 'src/Plugin.php';

add_action('plugins_loaded', static function (): void {
    \BricksGhlConnector\Plugin::boot();
});
