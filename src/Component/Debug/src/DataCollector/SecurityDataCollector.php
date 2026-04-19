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

namespace WPPack\Component\Debug\DataCollector;

use WPPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'security', priority: 85)]
final class SecurityDataCollector extends AbstractDataCollector
{
    /** @var list<array{action: string, operation: string, result: bool, timestamp: float}> */
    private array $nonceOperations = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'security';
    }

    public function getLabel(): string
    {
        return 'Security';
    }

    public function collect(): void
    {
        if (!is_user_logged_in()) {
            $this->data = $this->getAnonymousDefaults();
            return;
        }

        $user = wp_get_current_user();

        $this->data = [
            'is_logged_in' => true,
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'roles' => array_values($user->roles),
            'capabilities' => $user->allcaps,
            'is_super_admin' => $this->isSuperAdmin(),
            'authentication' => $this->detectAuthentication(),
            'nonce_operations' => $this->nonceOperations,
            'nonce_verify_count' => count($this->nonceOperations),
            'nonce_verify_failures' => count(array_filter($this->nonceOperations, static fn(array $op): bool => !$op['result'])),
        ];
    }

    public function getIndicatorValue(): string
    {
        if (!($this->data['is_logged_in'] ?? false)) {
            return 'anon.';
        }

        return $this->data['username'] ?? 'anon.';
    }

    public function getIndicatorColor(): string
    {
        $failures = (int) ($this->data['nonce_verify_failures'] ?? 0);

        return $failures > 0 ? 'red' : 'default';
    }

    /**
     * @param bool $result The nonce verification result.
     */
    public function captureNonceVerify(bool $result, string $action): void
    {
        $this->nonceOperations[] = [
            'action' => $action,
            'operation' => 'verify',
            'result' => $result,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * @param false|int $result
     * @return false|int
     */
    public function filterNonceVerify(false|int $result, string $nonce, string $action): false|int
    {
        $this->nonceOperations[] = [
            'action' => $action,
            'operation' => 'verify',
            'result' => $result !== false,
            'timestamp' => microtime(true),
        ];

        return $result;
    }

    public function reset(): void
    {
        parent::reset();
        $this->nonceOperations = [];
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
            'nonce_operations' => $this->nonceOperations,
            'nonce_verify_count' => count($this->nonceOperations),
            'nonce_verify_failures' => count(array_filter($this->nonceOperations, static fn(array $op): bool => !$op['result'])),
        ];
    }

    private function detectAuthentication(): string
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            if (is_string($authHeader) && str_starts_with(strtolower($authHeader), 'basic ')) {
                return 'application_password';
            }
        }

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
        return is_super_admin();
    }

    private function registerHooks(): void
    {
        add_filter('wp_verify_nonce', [$this, 'filterNonceVerify'], \PHP_INT_MAX, 3);
    }
}
