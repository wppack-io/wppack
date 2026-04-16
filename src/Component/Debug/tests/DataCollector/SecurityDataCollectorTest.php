<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\SecurityDataCollector;

final class SecurityDataCollectorTest extends TestCase
{
    private SecurityDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new SecurityDataCollector();
    }

    #[Test]
    public function getNameReturnsSecurity(): void
    {
        self::assertSame('security', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsSecurity(): void
    {
        self::assertSame('Security', $this->collector->getLabel());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function collectWithLoggedInUserReturnsUserData(): void
    {

        $userId = wp_insert_user([
            'user_login' => 'test_security_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'security_test@example.com',
        ]);

        wp_set_current_user($userId);

        try {
            $collector = new SecurityDataCollector();
            $collector->collect();
            $data = $collector->getData();

            self::assertTrue($data['is_logged_in']);
            self::assertSame($userId, $data['user_id']);
            self::assertNotEmpty($data['username']);
            self::assertNotEmpty($data['display_name']);
            self::assertSame('security_test@example.com', $data['email']);
            self::assertContains('administrator', $data['roles']);
            self::assertNotEmpty($data['capabilities']);
            self::assertSame('cookie', $data['authentication']);
            self::assertSame(0, $data['nonce_verify_count']);
            self::assertSame(0, $data['nonce_verify_failures']);
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function collectDetectsBasicAuthAsApplicationPassword(): void
    {

        $originalServer = $_SERVER;
        $userId = wp_insert_user([
            'user_login' => 'test_basic_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'basic@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

        try {
            $collector = new SecurityDataCollector();
            $collector->collect();
            $data = $collector->getData();

            self::assertTrue($data['is_logged_in']);
            self::assertSame('application_password', $data['authentication']);
        } finally {
            $_SERVER = $originalServer;
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function collectDetectsRedirectBasicAuthAsApplicationPassword(): void
    {

        $originalServer = $_SERVER;
        $userId = wp_insert_user([
            'user_login' => 'test_redirect_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'redirect@example.com',
        ]);

        wp_set_current_user($userId);
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

        try {
            $collector = new SecurityDataCollector();
            $collector->collect();
            $data = $collector->getData();

            self::assertSame('application_password', $data['authentication']);
        } finally {
            $_SERVER = $originalServer;
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function filterNonceVerifyRecordsOperationsInData(): void
    {
        $this->collector->filterNonceVerify(1, 'abc123', 'my_action');
        $this->collector->filterNonceVerify(false, 'def456', 'another_action');
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(2, $data['nonce_verify_count']);
        self::assertSame(1, $data['nonce_verify_failures']);
        self::assertCount(2, $data['nonce_operations']);
        self::assertSame('my_action', $data['nonce_operations'][0]['action']);
        self::assertTrue($data['nonce_operations'][0]['result']);
        self::assertSame('another_action', $data['nonce_operations'][1]['action']);
        self::assertFalse($data['nonce_operations'][1]['result']);
    }

    #[Test]
    public function captureNonceVerifyRecordsOperations(): void
    {
        $this->collector->captureNonceVerify(true, 'test_action');
        $this->collector->captureNonceVerify(false, 'failed_action');
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(2, $data['nonce_verify_count']);
        self::assertSame(1, $data['nonce_verify_failures']);
    }

    #[Test]
    public function getIndicatorValueReturnsUsernameWhenLoggedIn(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_logged_in' => true,
            'username' => 'admin',
        ]);

        self::assertSame('admin', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsRedWhenNonceFailures(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'nonce_verify_failures' => 2,
        ]);

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function collectIsSuperAdminWithWordPress(): void
    {

        $userId = wp_insert_user([
            'user_login' => 'test_super_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'super@example.com',
        ]);

        wp_set_current_user($userId);

        try {
            $collector = new SecurityDataCollector();
            $collector->collect();
            $data = $collector->getData();

            // is_super_admin uses function_exists guard
            self::assertIsBool($data['is_super_admin']);
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function resetClearsNonceOperationsWithData(): void
    {
        $this->collector->filterNonceVerify(1, 'nonce', 'action');
        $this->collector->collect();

        self::assertNotEmpty($this->collector->getData()['nonce_operations']);

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }
}
