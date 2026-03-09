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
        $userId = wp_insert_user([
            'user_login' => 'saml_test_email_' . uniqid(),
            'user_email' => 'saml-test-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $createdUser = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $createdUser);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            'some-name-id',
            ['email' => [$createdUser->user_email]],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
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
    public function autoProvision(): void
    {
        if (!function_exists('wp_insert_user')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $email = 'saml-provision-' . uniqid() . '@example.com';
        $resolver = new SamlUserResolver(autoProvision: true);

        $user = $resolver->resolveUser(
            'provisioned_user_' . uniqid(),
            ['email' => [$email]],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($email, $user->user_email);
    }
}
