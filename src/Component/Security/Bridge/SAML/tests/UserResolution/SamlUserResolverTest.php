<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\Tests\UserResolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolver;
use WpPack\Component\Security\Exception\AuthenticationException;

#[CoversClass(SamlUserResolver::class)]
final class SamlUserResolverTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('get_user_by')) {
            self::markTestSkipped('WordPress functions are not available.');
        }
    }

    #[Test]
    public function resolveUserByEmail(): void
    {
        $nameId = 'saml-nameid-' . uniqid();
        $userId = wp_insert_user([
            'user_login' => 'saml_test_email_' . uniqid(),
            'user_email' => 'saml-test-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $createdUser = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $createdUser);

        $resolver = new SamlUserResolver();

        // First resolve binds the NameID
        $user = $resolver->resolveUser(
            $nameId,
            ['email' => [$createdUser->user_email]],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
        self::assertSame($nameId, get_user_meta($userId, '_wppack_saml_nameid', true));
    }

    #[Test]
    public function resolveUserByEmailRejectsNameIdMismatch(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'saml_test_mismatch_' . uniqid(),
            'user_email' => 'saml-mismatch-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        // Bind a different NameID
        update_user_meta($userId, '_wppack_saml_nameid', 'original-nameid');

        $createdUser = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $createdUser);

        $resolver = new SamlUserResolver();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('NameID mismatch');

        $resolver->resolveUser(
            'different-nameid',
            ['email' => [$createdUser->user_email]],
        );
    }

    #[Test]
    public function resolveUserByNameId(): void
    {
        $login = 'saml_test_login_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-login-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $login,
            [],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function resolveUserNotFoundThrowsException(): void
    {
        $resolver = new SamlUserResolver(autoProvision: false);

        $this->expectException(AuthenticationException::class);

        $resolver->resolveUser(
            'nonexistent@example.com',
            ['email' => ['nonexistent-' . uniqid() . '@example.com']],
        );
    }

    #[Test]
    public function resolveUserRejectsEmptyNameId(): void
    {
        $resolver = new SamlUserResolver();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid SAML NameID');

        // Characters that sanitize_user(, true) strips entirely
        $resolver->resolveUser(
            '   ',
            ['email' => ['test@example.com']],
        );
    }

    #[Test]
    public function autoProvision(): void
    {
        if (!function_exists('wp_insert_user')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $email = 'saml-provision-' . uniqid() . '@example.com';
        $nameId = 'provisioned_user_' . uniqid();
        $resolver = new SamlUserResolver(autoProvision: true);

        $user = $resolver->resolveUser(
            $nameId,
            ['email' => [$email]],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($email, $user->user_email);
        self::assertSame($nameId, get_user_meta($user->ID, '_wppack_saml_nameid', true));
    }

    #[Test]
    public function resolveUserRejectsNullByteInNameId(): void
    {
        $resolver = new SamlUserResolver();

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid SAML NameID');

        // NameID consisting only of null bytes becomes empty after sanitize_user()
        $resolver->resolveUser(
            "\0\0\0",
            ['email' => ['test@example.com']],
        );
    }

    #[Test]
    public function resolveUserWithInvalidEmailSkipsEmailLookup(): void
    {
        $login = 'saml_invalidemail_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-invalidemail-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver();

        // Invalid email format should be skipped, resolver falls through to login lookup
        $user = $resolver->resolveUser(
            $login,
            ['email' => ['not-an-email']],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function autoProvisionWithXssInAttributes(): void
    {
        if (!function_exists('wp_insert_user')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $nameId = 'provisioned_xss_' . uniqid();
        $resolver = new SamlUserResolver(autoProvision: true);

        $user = $resolver->resolveUser(
            $nameId,
            [
                'email' => ['xss-' . uniqid() . '@example.com'],
                'displayName' => ['<script>alert("xss")</script>'],
                'firstName' => ['<img src=x onerror=alert(1)>'],
                'lastName' => ['<b>bold</b>'],
            ],
        );

        self::assertInstanceOf(\WP_User::class, $user);

        $refreshed = get_user_by('id', $user->ID);
        self::assertInstanceOf(\WP_User::class, $refreshed);
        self::assertStringNotContainsString('<script>', $refreshed->display_name);
        self::assertStringNotContainsString('<img', $refreshed->first_name);
        self::assertStringNotContainsString('<b>', $refreshed->last_name);
    }

    #[Test]
    public function roleMapFirstMatchWins(): void
    {
        $login = 'saml_role_first_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-role-first-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver(
            roleMapping: ['Admin' => 'administrator', 'Editor' => 'editor'],
            roleAttribute: 'groups',
        );

        $user = $resolver->resolveUser(
            $login,
            ['groups' => ['Editor', 'Admin']],
        );

        // First match (Editor) should win
        self::assertContains('editor', $user->roles);
    }
}
