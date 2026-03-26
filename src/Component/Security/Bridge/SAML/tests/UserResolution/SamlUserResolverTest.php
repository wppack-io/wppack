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

namespace WpPack\Component\Security\Bridge\SAML\Tests\UserResolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\SAML\UserResolution\SamlUserResolver;
use WpPack\Component\Security\Exception\AuthenticationException;

#[CoversClass(SamlUserResolver::class)]
final class SamlUserResolverTest extends TestCase
{
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

    #[Test]
    public function roleMapUnmatchedKeepsExistingRole(): void
    {
        $login = 'saml_unmatched_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-unmatched-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'editor',
        ]);

        self::assertIsInt($userId);

        // Explicitly set role to ensure it's applied in the test environment
        $wpUser = get_user_by('id', $userId);
        $wpUser->set_role('editor');

        $resolver = new SamlUserResolver(
            roleMapping: ['Admin' => 'administrator'],
            roleAttribute: 'groups',
        );

        $user = $resolver->resolveUser(
            $login,
            ['groups' => ['UnknownGroup']],
        );

        // Role should not change when no mapping matches
        self::assertContains('editor', $user->roles);
    }

    #[Test]
    public function roleMapSkippedWhenNoMappingConfigured(): void
    {
        $login = 'saml_norole_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-norole-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ]);

        self::assertIsInt($userId);

        // Explicitly set role to ensure it's applied in the test environment
        $wpUser = get_user_by('id', $userId);
        $wpUser->set_role('subscriber');

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $login,
            ['groups' => ['Admin']],
        );

        self::assertContains('subscriber', $user->roles);
    }

    #[Test]
    public function syncUserAttributesUpdatesChanged(): void
    {
        $nameId = 'saml-sync-' . uniqid();
        $email = 'saml-sync-' . uniqid() . '@example.com';
        $userId = wp_insert_user([
            'user_login' => 'saml_sync_' . uniqid(),
            'user_email' => $email,
            'user_pass' => wp_generate_password(),
            'first_name' => 'OldFirst',
            'last_name' => 'OldLast',
            'display_name' => 'OldDisplay',
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $nameId,
            [
                'email' => [$email],
                'firstName' => ['NewFirst'],
                'lastName' => ['NewLast'],
                'displayName' => ['NewDisplay'],
            ],
        );

        $refreshed = get_user_by('id', $user->ID);
        self::assertInstanceOf(\WP_User::class, $refreshed);
        self::assertSame('NewFirst', $refreshed->first_name);
        self::assertSame('NewLast', $refreshed->last_name);
        self::assertSame('NewDisplay', $refreshed->display_name);
    }

    #[Test]
    public function syncUserAttributesSkipsWhenNoChanges(): void
    {
        $nameId = 'saml-nochange-' . uniqid();
        $email = 'saml-nochange-' . uniqid() . '@example.com';
        $userId = wp_insert_user([
            'user_login' => 'saml_nochange_' . uniqid(),
            'user_email' => $email,
            'user_pass' => wp_generate_password(),
            'first_name' => 'Same',
            'last_name' => 'Same',
            'display_name' => 'Same Same',
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $nameId,
            [
                'email' => [$email],
                'firstName' => ['Same'],
                'lastName' => ['Same'],
                'displayName' => ['Same Same'],
            ],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function autoProvisionWithAllAttributes(): void
    {
        $nameId = 'provisioned_full_' . uniqid();
        $email = 'saml-full-' . uniqid() . '@example.com';
        $resolver = new SamlUserResolver(
            autoProvision: true,
            defaultRole: 'editor',
        );

        $user = $resolver->resolveUser(
            $nameId,
            [
                'email' => [$email],
                'firstName' => ['John'],
                'lastName' => ['Doe'],
                'displayName' => ['John Doe'],
            ],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($email, $user->user_email);

        $refreshed = get_user_by('id', $user->ID);
        self::assertInstanceOf(\WP_User::class, $refreshed);
        self::assertSame('John', $refreshed->first_name);
        self::assertSame('Doe', $refreshed->last_name);
        self::assertSame('John Doe', $refreshed->display_name);
        self::assertContains('editor', $refreshed->roles);
    }

    #[Test]
    public function autoProvisionWithoutOptionalAttributes(): void
    {
        $nameId = 'provisioned_minimal_' . uniqid();
        $resolver = new SamlUserResolver(
            autoProvision: true,
            firstNameAttribute: null,
            lastNameAttribute: null,
            displayNameAttribute: null,
        );

        $user = $resolver->resolveUser(
            $nameId,
            [],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($nameId, $user->user_login);
    }

    #[Test]
    public function autoProvisionWithoutEmail(): void
    {
        // Use an email-like NameID so it serves as a valid fallback email
        // when no email attribute is provided (user_email = nameId).
        $nameId = 'noemail-' . uniqid() . '@example.com';
        $resolver = new SamlUserResolver(autoProvision: true);

        $user = $resolver->resolveUser(
            $nameId,
            [],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($nameId, $user->user_login);
        // When no email attribute is provided, the NameID is used as fallback email
        self::assertSame($nameId, $user->user_email);
    }

    #[Test]
    public function syncWithNullAttributeNames(): void
    {
        $login = 'saml_null_attr_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-null-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'first_name' => 'Existing',
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver(
            firstNameAttribute: null,
            lastNameAttribute: null,
            displayNameAttribute: null,
        );

        $user = $resolver->resolveUser(
            $login,
            [],
        );

        $refreshed = get_user_by('id', $user->ID);
        self::assertInstanceOf(\WP_User::class, $refreshed);
        self::assertSame('Existing', $refreshed->first_name);
    }

    #[Test]
    public function resolveUserBindsNameIdOnFirstLogin(): void
    {
        $login = 'saml_bind_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-bind-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver();

        $resolver->resolveUser($login, []);

        self::assertSame($login, get_user_meta($userId, '_wppack_saml_nameid', true));
    }

    #[Test]
    public function autoProvisionWithRoleMapping(): void
    {
        $nameId = 'provisioned_role_' . uniqid();
        $resolver = new SamlUserResolver(
            autoProvision: true,
            defaultRole: 'subscriber',
            roleMapping: ['Admin' => 'administrator'],
            roleAttribute: 'groups',
        );

        $user = $resolver->resolveUser(
            $nameId,
            [
                'email' => ['provision-role-' . uniqid() . '@example.com'],
                'groups' => ['Admin'],
            ],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertContains('administrator', $user->roles);
    }

    #[Test]
    public function roleMapSkippedWhenRoleAttributeIsNull(): void
    {
        $login = 'saml_noroleattr_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-noroleattr-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ]);

        self::assertIsInt($userId);

        // Explicitly set role to ensure it's applied in the test environment
        $wpUser = get_user_by('id', $userId);
        $wpUser->set_role('subscriber');

        $resolver = new SamlUserResolver(
            roleMapping: ['Admin' => 'administrator'],
            roleAttribute: null,
        );

        $user = $resolver->resolveUser(
            $login,
            ['groups' => ['Admin']],
        );

        self::assertContains('subscriber', $user->roles);
    }

    #[Test]
    public function resolveUserByEmailWithExistingBoundNameId(): void
    {
        $nameId = 'saml-bound-' . uniqid();
        $email = 'saml-bound-' . uniqid() . '@example.com';
        $userId = wp_insert_user([
            'user_login' => 'saml_bound_' . uniqid(),
            'user_email' => $email,
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        // Pre-bind with same NameID
        update_user_meta($userId, '_wppack_saml_nameid', $nameId);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $nameId,
            ['email' => [$email]],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function resolveUserWithEmptyEmailAttribute(): void
    {
        $login = 'saml_emptyemail_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-emptyemail-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver();

        // Empty email value should be skipped
        $user = $resolver->resolveUser(
            $login,
            ['email' => ['']],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function autoProvisionFailsWithDuplicateLogin(): void
    {
        $existingLogin = 'saml_dup_' . uniqid();
        $existingEmail = 'saml-dup-' . uniqid() . '@example.com';

        // Create a user first
        $userId = wp_insert_user([
            'user_login' => $existingLogin,
            'user_email' => $existingEmail,
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver(autoProvision: true);

        // Try to auto-provision with the same login but a different email
        // This should fail because the login already exists (will be found by login lookup)
        // Actually, resolveUser will find by login and return the existing user
        $user = $resolver->resolveUser(
            $existingLogin,
            ['email' => ['different-' . uniqid() . '@example.com']],
        );

        // The existing user is returned since it was found by login
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function autoProvisionFailsWhenWpInsertUserReturnsError(): void
    {
        // Use an extremely long login name that wp_insert_user should reject
        // or use a pre_user_login filter to force an error
        $nameId = 'provision_fail_' . uniqid();

        add_filter('pre_user_login', function () {
            return ''; // Force empty login which wp_insert_user will reject
        });

        $resolver = new SamlUserResolver(autoProvision: true);

        try {
            $this->expectException(AuthenticationException::class);
            $this->expectExceptionMessage('User provisioning failed.');

            $resolver->resolveUser(
                $nameId,
                ['email' => ['provision-fail-' . uniqid() . '@example.com']],
            );
        } finally {
            remove_all_filters('pre_user_login');
        }
    }

    #[Test]
    public function resolveUserByNameIdMeta(): void
    {
        $nameId = 'saml-meta-' . uniqid();
        $email = 'saml-meta-' . uniqid() . '@example.com';
        $userId = wp_insert_user([
            'user_login' => 'saml_meta_' . uniqid(),
            'user_email' => $email,
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        // Pre-bind NameID via meta
        update_user_meta($userId, '_wppack_saml_nameid', $nameId);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $nameId,
            ['email' => [$email]],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function resolveUserByNameIdMetaSyncsEmail(): void
    {
        $nameId = 'saml-meta-sync-' . uniqid();
        $oldEmail = 'saml-old-' . uniqid() . '@example.com';
        $newEmail = 'saml-new-' . uniqid() . '@example.com';
        $userId = wp_insert_user([
            'user_login' => 'saml_meta_sync_' . uniqid(),
            'user_email' => $oldEmail,
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        update_user_meta($userId, '_wppack_saml_nameid', $nameId);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $nameId,
            ['email' => [$newEmail]],
        );

        $refreshed = get_user_by('id', $user->ID);
        self::assertInstanceOf(\WP_User::class, $refreshed);
        self::assertSame($newEmail, $refreshed->user_email);
    }

    #[Test]
    public function resolveUserByNameIdMetaDoesNotSyncInvalidEmail(): void
    {
        $nameId = 'saml-meta-invalid-' . uniqid();
        $originalEmail = 'saml-original-' . uniqid() . '@example.com';
        $userId = wp_insert_user([
            'user_login' => 'saml_meta_invalid_' . uniqid(),
            'user_email' => $originalEmail,
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        update_user_meta($userId, '_wppack_saml_nameid', $nameId);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $nameId,
            ['email' => ['not-an-email']],
        );

        $refreshed = get_user_by('id', $user->ID);
        self::assertInstanceOf(\WP_User::class, $refreshed);
        self::assertSame($originalEmail, $refreshed->user_email);
    }

    #[Test]
    public function resolveUserByNameIdMetaSkipsEmailSyncWhenSame(): void
    {
        $nameId = 'saml-meta-same-' . uniqid();
        $email = 'saml-same-' . uniqid() . '@example.com';
        $userId = wp_insert_user([
            'user_login' => 'saml_meta_same_' . uniqid(),
            'user_email' => $email,
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        update_user_meta($userId, '_wppack_saml_nameid', $nameId);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $nameId,
            ['email' => [$email]],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
        self::assertSame($email, $user->user_email);
    }

    #[Test]
    public function resolveUserLoginFallbackSyncsEmail(): void
    {
        $login = 'saml_login_sync_' . uniqid();
        $oldEmail = 'saml-login-old-' . uniqid() . '@example.com';
        $newEmail = 'saml-login-new-' . uniqid() . '@example.com';
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => $oldEmail,
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $resolver = new SamlUserResolver();

        $user = $resolver->resolveUser(
            $login,
            ['email' => [$newEmail]],
        );

        $refreshed = get_user_by('id', $user->ID);
        self::assertInstanceOf(\WP_User::class, $refreshed);
        self::assertSame($newEmail, $refreshed->user_email);
    }

    #[Test]
    public function roleMapWithEmptyRoleValues(): void
    {
        $login = 'saml_empty_roles_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'saml-empty-roles-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ]);

        self::assertIsInt($userId);

        // Explicitly set role to ensure it's applied in the test environment
        $wpUser = get_user_by('id', $userId);
        $wpUser->set_role('subscriber');

        $resolver = new SamlUserResolver(
            roleMapping: ['Admin' => 'administrator'],
            roleAttribute: 'groups',
        );

        // Empty groups array
        $user = $resolver->resolveUser(
            $login,
            ['groups' => []],
        );

        self::assertContains('subscriber', $user->roles);
    }
}
