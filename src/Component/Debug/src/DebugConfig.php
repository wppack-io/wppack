<?php

declare(strict_types=1);

namespace WpPack\Component\Debug;

final readonly class DebugConfig
{
    /**
     * @param list<string> $ipWhitelist
     * @param list<string> $roleWhitelist
     */
    public function __construct(
        public bool $enabled = false,
        public bool $showToolbar = false,
        public array $ipWhitelist = ['127.0.0.1', '::1'],
        public array $roleWhitelist = ['administrator'],
    ) {}

    public function isEnabled(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (defined('WP_DEBUG') && !WP_DEBUG) {
            return false;
        }

        return true;
    }

    public function shouldShowToolbar(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if (!$this->showToolbar) {
            return false;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return false;
        }

        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        return true;
    }

    public function isAllowedIp(string $ip): bool
    {
        return in_array($ip, $this->ipWhitelist, true);
    }
}
