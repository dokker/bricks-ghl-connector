<?php

declare(strict_types=1);

namespace BricksGhlConnector\Admin;

final class SettingsPage
{
    private SettingsRepository $settings;

    public function __construct(SettingsRepository $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addPage(): void
    {
        add_options_page(
            __('Bricks GHL Connector', 'bricks-ghl-connector'),
            __('Bricks GHL Connector', 'bricks-ghl-connector'),
            'manage_options',
            'bricks-ghl-connector',
            [$this, 'render']
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            'bghl_connector',
            SettingsRepository::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => function ($input): array {
                    return $this->settings->sanitize(is_array($input) ? $input : []);
                },
                'default' => $this->settings->defaults(),
            ]
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings->all();
        $option = SettingsRepository::OPTION_NAME;
        $tokenConfigured = $this->settings->apiToken() !== '';
        $tokenDefinedInConfig = $this->settings->isApiTokenDefinedInConfig();
        $locationDefinedInConfig = $this->settings->isLocationIdDefinedInConfig();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Bricks GHL Connector', 'bricks-ghl-connector'); ?></h1>
            <?php if ($tokenDefinedInConfig || $locationDefinedInConfig) : ?>
                <div class="notice notice-info inline">
                    <p><?php echo esc_html__('One or more GHL credentials are defined in wp-config.php and override the saved settings below.', 'bricks-ghl-connector'); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" action="options.php">
                <?php settings_fields('bghl_connector'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="bghl-api-token"><?php echo esc_html__('GHL API token', 'bricks-ghl-connector'); ?></label>
                        </th>
                        <td>
                            <input
                                id="bghl-api-token"
                                class="regular-text"
                                type="password"
                                name="<?php echo esc_attr($option); ?>[api_token]"
                                value=""
                                autocomplete="new-password"
                                placeholder="<?php echo $tokenConfigured ? esc_attr__('Token is configured. Leave empty to keep it.', 'bricks-ghl-connector') : ''; ?>"
                                <?php disabled($tokenDefinedInConfig); ?>
                            >
                            <p class="description"><?php echo esc_html__('Use the sub-account API key or private integration token for this WordPress site.', 'bricks-ghl-connector'); ?></p>
                            <?php if ($tokenDefinedInConfig) : ?>
                                <p class="description"><?php echo esc_html__('The API token is currently loaded from BRICKS_GHL_CONNECTOR_API_TOKEN in wp-config.php.', 'bricks-ghl-connector'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bghl-location-id"><?php echo esc_html__('Location ID', 'bricks-ghl-connector'); ?></label>
                        </th>
                        <td>
                            <input id="bghl-location-id" class="regular-text" type="text" name="<?php echo esc_attr($option); ?>[location_id]" value="<?php echo esc_attr((string) $settings['location_id']); ?>" <?php disabled($locationDefinedInConfig); ?>>
                            <?php if ($locationDefinedInConfig) : ?>
                                <p class="description"><?php echo esc_html__('The Location ID is currently loaded from BRICKS_GHL_CONNECTOR_LOCATION_ID in wp-config.php.', 'bricks-ghl-connector'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bghl-default-source"><?php echo esc_html__('Default source', 'bricks-ghl-connector'); ?></label>
                        </th>
                        <td>
                            <input id="bghl-default-source" class="regular-text" type="text" name="<?php echo esc_attr($option); ?>[default_source]" value="<?php echo esc_attr((string) $settings['default_source']); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="bghl-default-tags"><?php echo esc_html__('Default tags', 'bricks-ghl-connector'); ?></label>
                        </th>
                        <td>
                            <input id="bghl-default-tags" class="regular-text" type="text" name="<?php echo esc_attr($option); ?>[default_tags]" value="<?php echo esc_attr((string) $settings['default_tags']); ?>">
                            <p class="description"><?php echo esc_html__('Comma-separated tags added to every contact created from a Bricks form.', 'bricks-ghl-connector'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Debug logging', 'bricks-ghl-connector'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr($option); ?>[debug_enabled]" value="1" <?php checked((bool) $settings['debug_enabled']); ?>>
                                <?php echo esc_html__('Log connector events to the WordPress debug log.', 'bricks-ghl-connector'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
