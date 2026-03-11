<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'user', priority: 85)]
final class UserDataCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'user';
    }

    public function getLabel(): string
    {
        return 'User';
    }

    public function collect(): void
    {
        if (!function_exists('is_user_logged_in') || !is_user_logged_in()) {
            $this->data = $this->getAnonymousDefaults();

            return;
        }

        $user = wp_get_current_user();

        $this->data = [
            'is_logged_in' => true,
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'email' => $this->maskEmail($user->user_email),
            'roles' => array_values($user->roles),
            'capabilities' => $user->allcaps,
            'is_super_admin' => $this->isSuperAdmin(),
            'authentication' => $this->detectAuthentication(),
        ];
    }

    public function getBadgeValue(): string
    {
        if (!($this->data['is_logged_in'] ?? false)) {
            return 'anon.';
        }

        return $this->data['username'] ?? 'anon.';
    }

    public function getBadgeColor(): string
    {
        return 'default';
    }

    /**
     * Mask an email address to show only the domain part.
     */
    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2) {
            return '***';
        }

        return '***@' . $parts[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function getAnonymousDefaults(): array
    {
        return [
            'is_logged_in' => false,
            'user_id' => 0,
            'username' => '',
            'display_name' => '',
            'email' => '',
            'roles' => [],
            'capabilities' => [],
            'is_super_admin' => false,
            'authentication' => 'none',
        ];
    }

    /**
     * Detect the authentication method used for the current request.
     */
    private function detectAuthentication(): string
    {
        // Check for application password via HTTP_AUTHORIZATION header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (is_string($authHeader) && str_starts_with(strtolower($authHeader), 'basic ')) {
                return 'application_password';
            }
        }

        // Check for REDIRECT_HTTP_AUTHORIZATION (used by some server configurations)
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            if (is_string($authHeader) && str_starts_with(strtolower($authHeader), 'basic ')) {
                return 'application_password';
            }
        }

        return 'cookie';
    }

    private function isSuperAdmin(): bool
    {
        if (function_exists('is_super_admin')) {
            return is_super_admin();
        }

        return false;
    }
}
