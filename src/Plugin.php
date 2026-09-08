<?php

declare(strict_types=1);

namespace BricksGhlConnector;

use BricksGhlConnector\Admin\SettingsPage;
use BricksGhlConnector\Admin\SettingsRepository;
use BricksGhlConnector\Bricks\FormActionRegistrar;
use BricksGhlConnector\Bricks\FormControls;
use BricksGhlConnector\Bricks\FormSubmitHandler;
use BricksGhlConnector\Bricks\TrackingScript;
use BricksGhlConnector\GHL\Client;
use BricksGhlConnector\GHL\ContactPayloadBuilder;
use BricksGhlConnector\Mapping\FieldMapper;
use BricksGhlConnector\Mapping\MappingValidator;
use BricksGhlConnector\Support\Logger;

final class Plugin
{
    public static function boot(): void
    {
        $settings = new SettingsRepository();
        $logger = new Logger($settings);

        (new SettingsPage($settings))->register();

        add_action('after_setup_theme', static function () use ($settings, $logger): void {
            self::registerBricksIntegration($settings, $logger);
        }, 20);
    }

    private static function registerBricksIntegration(SettingsRepository $settings, Logger $logger): void
    {
        if (! self::isBricksActive()) {
            add_action('admin_notices', static function (): void {
                if (! current_user_can('activate_plugins')) {
                    return;
                }

                echo '<div class="notice notice-warning"><p>';
                echo esc_html__('Bricks GHL Connector is active, but Bricks Builder was not detected. The GHL form action will become available when Bricks is active.', 'bricks-ghl-connector');
                echo '</p></div>';
            });

            return;
        }

        $handler = new FormSubmitHandler(
            $settings,
            new MappingValidator(),
            new FieldMapper(),
            new ContactPayloadBuilder(),
            new Client($settings, $logger),
            $logger
        );

        $registrar = new FormActionRegistrar(
            new FormControls(),
            $handler,
            new TrackingScript($settings)
        );

        $registrar->register();
    }

    private static function isBricksActive(): bool
    {
        $theme = wp_get_theme();
        $template = strtolower((string) $theme->get_template());

        return defined('BRICKS_VERSION')
            || $template === 'bricks'
            || class_exists('\Bricks\Integrations\Form\Init')
            || class_exists('\Bricks\Elements');
    }
}
