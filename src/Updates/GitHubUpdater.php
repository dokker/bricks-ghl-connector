<?php

declare(strict_types=1);

namespace BricksGhlConnector\Updates;

use BricksGhlConnector\Support\Logger;

/**
 * Offers plugin updates from the info.json asset of the latest GitHub release.
 *
 * The file is fetched from github.com/.../releases/latest/download/, which is a
 * plain file download and not a REST API call, so the GitHub API rate limit does
 * not apply. The release workflow builds info.json, see .github/scripts/build-info.py.
 */
final class GitHubUpdater
{
    private const REPOSITORY_URL = 'https://github.com/dokker/bricks-ghl-connector';
    private const INFO_URL = self::REPOSITORY_URL . '/releases/latest/download/info.json';
    private const CACHE_KEY = 'bghl_connector_update_info';
    private const CACHE_TTL = 12 * 3600;
    private const ERROR_CACHE_TTL = 3600;
    private const SECTIONS = ['description', 'installation', 'faq', 'changelog', 'other_notes'];

    private string $pluginFile;
    private string $slug;
    private Logger $logger;

    public function __construct(string $pluginFile, Logger $logger)
    {
        $this->pluginFile = plugin_basename($pluginFile);
        $this->slug = dirname($this->pluginFile);
        $this->logger = $logger;
    }

    public function register(): void
    {
        // The hostname comes from the Update URI plugin header.
        add_filter('update_plugins_github.com', [$this, 'checkForUpdate'], 10, 3);
        add_filter('plugins_api', [$this, 'pluginInformation'], 10, 3);
    }

    /**
     * WordPress compares the returned version with the installed one.
     *
     * @param array<string, mixed>|false $update
     * @param array<string, mixed> $pluginData
     * @return array<string, mixed>|false
     */
    public function checkForUpdate($update, array $pluginData, string $pluginFile)
    {
        if ($pluginFile !== $this->pluginFile) {
            return $update;
        }

        $info = $this->getInfo();

        if ($info === null) {
            return $update;
        }

        return [
            'slug' => $this->slug,
            'version' => $info['version'],
            'url' => self::REPOSITORY_URL,
            'package' => $info['download_url'],
            'requires' => $info['requires'],
            'requires_php' => $info['requires_php'],
            'tested' => $info['tested'],
        ];
    }

    /**
     * Fills the "View details" modal.
     *
     * @param false|object|array<string, mixed> $result
     * @param object $args
     * @return false|object|array<string, mixed>|\WP_Error
     */
    public function pluginInformation($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || ! isset($args->slug) || $args->slug !== $this->slug) {
            return $result;
        }

        $info = $this->getInfo();

        // Never fall through to wordpress.org, a plugin there may use the same slug.
        if ($info === null) {
            return new \WP_Error(
                'plugins_api_failed',
                __('The update information for Bricks GHL Connector could not be loaded from GitHub.', 'bricks-ghl-connector')
            );
        }

        return (object) [
            'name' => $info['name'],
            'slug' => $this->slug,
            'version' => $info['version'],
            'author' => $info['author'],
            'homepage' => self::REPOSITORY_URL,
            'requires' => $info['requires'],
            'requires_php' => $info['requires_php'],
            'tested' => $info['tested'],
            'last_updated' => $info['last_updated'],
            'download_link' => $info['download_url'],
            'sections' => $info['sections'],
        ];
    }

    /** @return array<string, mixed>|null */
    private function getInfo(): ?array
    {
        $cached = get_site_transient(self::CACHE_KEY);

        if (is_array($cached) && array_key_exists('info', $cached) && ! $this->isForceCheck()) {
            return $cached['info'];
        }

        $info = $this->fetchInfo();
        set_site_transient(self::CACHE_KEY, ['info' => $info], $info === null ? self::ERROR_CACHE_TTL : self::CACHE_TTL);

        return $info;
    }

    /** "Check again" on Dashboard > Updates bypasses the cache. */
    private function isForceCheck(): bool
    {
        return is_admin()
            && ! empty($_GET['force-check']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            && current_user_can('update_plugins');
    }

    /** @return array<string, mixed>|null */
    private function fetchInfo(): ?array
    {
        $response = wp_remote_get(self::INFO_URL, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            $this->logger->debug('Update info request failed.', ['error' => $response->get_error_message()]);

            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        if ($status !== 200) {
            $this->logger->debug('Update info request returned an unexpected status.', ['status' => $status]);

            return null;
        }

        $info = $this->normalize(json_decode(wp_remote_retrieve_body($response), true));

        if ($info === null) {
            $this->logger->debug('Update info is malformed.');
        }

        return $info;
    }

    /**
     * @param mixed $data
     * @return array<string, mixed>|null
     */
    private function normalize($data): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        $version = is_string($data['version'] ?? null) ? $data['version'] : '';
        $downloadUrl = is_string($data['download_url'] ?? null) ? $data['download_url'] : '';

        // Only accept packages from this repository's releases.
        if (
            preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1
            || strpos($downloadUrl, self::REPOSITORY_URL . '/releases/download/') !== 0
        ) {
            return null;
        }

        $sections = [];

        foreach (self::SECTIONS as $section) {
            if (is_string($data['sections'][$section] ?? null)) {
                $sections[$section] = wp_kses_post($data['sections'][$section]);
            }
        }

        return [
            'name' => $this->string($data, 'name', 'Bricks GHL Connector'),
            'version' => $version,
            'author' => $this->string($data, 'author'),
            'download_url' => esc_url_raw($downloadUrl),
            'requires' => $this->string($data, 'requires'),
            'requires_php' => $this->string($data, 'requires_php'),
            'tested' => $this->string($data, 'tested'),
            'last_updated' => $this->string($data, 'last_updated'),
            'sections' => $sections,
        ];
    }

    /** @param array<string, mixed> $data */
    private function string(array $data, string $key, string $default = ''): string
    {
        return is_string($data[$key] ?? null) ? sanitize_text_field($data[$key]) : $default;
    }
}
