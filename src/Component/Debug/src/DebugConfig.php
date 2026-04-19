<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Debug;

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

        // Hard-block in production environment
        if (wp_get_environment_type() === 'production') {
            return false;
        }

        return true;
    }

    /**
     * Check if the current request is allowed to access debug features.
     *
     * Combines isEnabled(), IP whitelist, and role whitelist checks.
     */
    public function isAccessAllowed(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($ip !== '' && !$this->isAllowedIp($ip)) {
            return false;
        }

        if (!$this->isAllowedRole()) {
            return false;
        }

        return true;
    }

    public function shouldShowToolbar(): bool
    {
        if (!$this->isAccessAllowed()) {
            return false;
        }

        if (!$this->showToolbar) {
            return false;
        }

        if (wp_doing_ajax()) {
            return false;
        }

        if (wp_doing_cron()) {
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

    public function isAllowedRole(): bool
    {
        if ($this->roleWhitelist === []) {
            return true;
        }

        foreach ($this->roleWhitelist as $role) {
            if (current_user_can($role)) {
                return true;
            }
        }

        return false;
    }
}
