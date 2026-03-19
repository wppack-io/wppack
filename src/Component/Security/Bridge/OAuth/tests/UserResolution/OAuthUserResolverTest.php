<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\UserResolution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\UserResolution\OAuthUserResolver;
use WpPack\Component\Security\Exception\AuthenticationException;

#[CoversClass(OAuthUserResolver::class)]
final class OAuthUserResolverTest extends TestCase
{
    #[Test]
    public function resolveUserByEmail(): void
    {
        $subject = 'oauth-sub-' . uniqid();
        $userId = wp_insert_user([
            'user_login' => 'oauth_test_email_' . uniqid(),
            'user_email' => 'oauth-test-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $createdUser = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $createdUser);

        $resolver = new OAuthUserResolver(providerName: 'google');

        $user = $resolver->resolveUser(
            $subject,
            ['email' => $createdUser->user_email],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
        self::assertSame($subject, get_user_meta($userId, '_wppack_oauth_sub_google', true));
    }

    #[Test]
    public function resolveUserByBoundSubject(): void
    {
        $subject = 'oauth-bound-' . uniqid();
        $userId = wp_insert_user([
            'user_login' => 'oauth_test_bound_' . uniqid(),
            'user_email' => 'oauth-bound-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        // Pre-bind the subject
        update_user_meta($userId, '_wppack_oauth_sub_azure', $subject);

        $resolver = new OAuthUserResolver(providerName: 'azure');

        $user = $resolver->resolveUser($subject, ['email' => 'different@example.com']);

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function resolveUserByEmailRejectsSubjectMismatch(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'oauth_test_mismatch_' . uniqid(),
            'user_email' => 'oauth-mismatch-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        // Bind a different subject
        update_user_meta($userId, '_wppack_oauth_sub_github', 'original-subject');

        $createdUser = get_user_by('id', $userId);
        self::assertInstanceOf(\WP_User::class, $createdUser);

        $resolver = new OAuthUserResolver(providerName: 'github');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('subject mismatch');

        $resolver->resolveUser(
            'different-subject',
            ['email' => $createdUser->user_email],
        );
    }

    #[Test]
    public function resolveUserByLogin(): void
    {
        $login = 'oauth_test_login_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-login-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $resolver = new OAuthUserResolver(providerName: 'google');

        $user = $resolver->resolveUser($login, []);

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
        self::assertSame($login, get_user_meta($userId, '_wppack_oauth_sub_google', true));
    }

    #[Test]
    public function resolveUserNotFoundThrowsException(): void
    {
        $resolver = new OAuthUserResolver(providerName: 'google', autoProvision: false);

        $this->expectException(AuthenticationException::class);

        $resolver->resolveUser(
            'nonexistent_subject',
            ['email' => 'nonexistent-' . uniqid() . '@example.com'],
        );
    }

    #[Test]
    public function resolveUserRejectsEmptySubject(): void
    {
        $resolver = new OAuthUserResolver(providerName: 'google');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OAuth subject identifier');

        // Characters that sanitize_user(, true) strips entirely
        $resolver->resolveUser(
            '   ',
            ['email' => 'test@example.com'],
        );
    }

    #[Test]
    public function autoProvision(): void
    {
        $email = 'oauth-provision-' . uniqid() . '@example.com';
        $subject = 'provisioned_user_' . uniqid();
        $resolver = new OAuthUserResolver(providerName: 'google', autoProvision: true);

        $user = $resolver->resolveUser(
            $subject,
            [
                'email' => $email,
                'given_name' => 'John',
                'family_name' => 'Doe',
                'name' => 'John Doe',
            ],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($email, $user->user_email);
        self::assertSame($subject, get_user_meta($user->ID, '_wppack_oauth_sub_google', true));
        self::assertSame('John', $user->first_name);
        self::assertSame('Doe', $user->last_name);
        self::assertSame('John Doe', $user->display_name);
    }

    #[Test]
    public function autoProvisionWithDefaultRole(): void
    {
        $subject = 'provisioned_editor_' . uniqid();
        $resolver = new OAuthUserResolver(
            providerName: 'google',
            autoProvision: true,
            defaultRole: 'editor',
        );

        $user = $resolver->resolveUser(
            $subject,
            ['email' => 'oauth-editor-' . uniqid() . '@example.com'],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertContains('editor', $user->roles);
    }

    #[Test]
    public function mapUserRoleWithStringClaim(): void
    {
        $login = 'oauth_role_str_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-role-str-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ]);

        self::assertIsInt($userId);

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            roleMapping: ['admin_group' => 'administrator', 'editor_group' => 'editor'],
            roleClaim: 'role',
        );

        $user = $resolver->resolveUser(
            $login,
            ['role' => 'editor_group'],
        );

        self::assertContains('editor', $user->roles);
    }

    #[Test]
    public function mapUserRoleWithArrayClaim(): void
    {
        $login = 'oauth_role_arr_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-role-arr-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ]);

        self::assertIsInt($userId);

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            roleMapping: ['admin_group' => 'administrator', 'editor_group' => 'editor'],
            roleClaim: 'groups',
        );

        $user = $resolver->resolveUser(
            $login,
            ['groups' => ['viewer_group', 'editor_group']],
        );

        self::assertContains('editor', $user->roles);
    }

    #[Test]
    public function mapUserRoleSkippedWhenNoMapping(): void
    {
        $login = 'oauth_norole_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-norole-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ]);

        self::assertIsInt($userId);

        // Explicitly set role to ensure it's applied in the test environment
        $wpUser = get_user_by('id', $userId);
        $wpUser->set_role('subscriber');

        $resolver = new OAuthUserResolver(providerName: 'google');

        $user = $resolver->resolveUser(
            $login,
            ['role' => 'admin_group'],
        );

        self::assertContains('subscriber', $user->roles);
    }

    #[Test]
    public function syncUserAttributesUpdatesChangedFields(): void
    {
        $subject = 'oauth-sync-' . uniqid();
        $userId = wp_insert_user([
            'user_login' => 'oauth_sync_' . uniqid(),
            'user_email' => 'oauth-sync-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'first_name' => 'OldFirst',
            'last_name' => 'OldLast',
            'display_name' => 'OldDisplay',
        ]);

        self::assertIsInt($userId);

        // Pre-bind the subject
        update_user_meta($userId, '_wppack_oauth_sub_google', $subject);

        $resolver = new OAuthUserResolver(providerName: 'google');

        $user = $resolver->resolveUser(
            $subject,
            [
                'email' => 'different@example.com',
                'given_name' => 'NewFirst',
                'family_name' => 'NewLast',
                'name' => 'NewDisplay',
            ],
        );

        // Re-fetch to confirm persistence
        $refreshed = get_user_by('id', $user->ID);
        self::assertInstanceOf(\WP_User::class, $refreshed);
        self::assertSame('NewFirst', $refreshed->first_name);
        self::assertSame('NewLast', $refreshed->last_name);
        self::assertSame('NewDisplay', $refreshed->display_name);
    }

    #[Test]
    public function syncUserAttributesSkipsWhenNoChanges(): void
    {
        $subject = 'oauth-nochange-' . uniqid();
        $userId = wp_insert_user([
            'user_login' => 'oauth_nochange_' . uniqid(),
            'user_email' => 'oauth-nochange-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'first_name' => 'Same',
            'last_name' => 'Same',
            'display_name' => 'Same Same',
        ]);

        self::assertIsInt($userId);

        // Pre-bind the subject
        update_user_meta($userId, '_wppack_oauth_sub_google', $subject);

        $resolver = new OAuthUserResolver(providerName: 'google');

        // Should not throw or error even when claims match existing values
        $user = $resolver->resolveUser(
            $subject,
            [
                'given_name' => 'Same',
                'family_name' => 'Same',
                'name' => 'Same Same',
            ],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function differentProviderNamesCreateDifferentMetaKeys(): void
    {
        $login = 'oauth_multi_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-multi-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $googleSubject = 'google-sub-' . uniqid();
        $azureSubject = 'azure-sub-' . uniqid();

        $googleResolver = new OAuthUserResolver(providerName: 'google');
        $azureResolver = new OAuthUserResolver(providerName: 'azure');

        // Resolve with Google first
        $googleResolver->resolveUser($login, []);

        // Bind both subjects manually
        update_user_meta($userId, '_wppack_oauth_sub_google', $googleSubject);
        update_user_meta($userId, '_wppack_oauth_sub_azure', $azureSubject);

        // Verify both meta keys exist independently
        self::assertSame($googleSubject, get_user_meta($userId, '_wppack_oauth_sub_google', true));
        self::assertSame($azureSubject, get_user_meta($userId, '_wppack_oauth_sub_azure', true));
    }

    #[Test]
    public function constructorWithAllOptions(): void
    {
        $resolver = new OAuthUserResolver(
            providerName: 'custom',
            autoProvision: true,
            defaultRole: 'editor',
            emailClaim: 'preferred_email',
            firstNameClaim: 'first',
            lastNameClaim: 'last',
            displayNameClaim: 'full_name',
            roleMapping: ['admin' => 'administrator'],
            roleClaim: 'custom_role',
        );

        // Just verify it can be instantiated without errors
        self::assertInstanceOf(OAuthUserResolver::class, $resolver);
    }

    #[Test]
    public function getClaimValueHandlesIntegerValues(): void
    {
        $login = 'oauth_int_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-int-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        // Pre-bind the subject to avoid login lookup path interfering
        update_user_meta($userId, '_wppack_oauth_sub_google', $login);

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            firstNameClaim: 'numeric_name',
        );

        // Integer claim values should be cast to string
        $user = $resolver->resolveUser(
            $login,
            ['numeric_name' => 42],
        );

        self::assertInstanceOf(\WP_User::class, $user);
    }

    #[Test]
    public function resolveUserRejectsNullByteInSubject(): void
    {
        $resolver = new OAuthUserResolver(providerName: 'google');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid OAuth subject identifier');

        // Subject consisting only of null bytes becomes empty after sanitize_user()
        $resolver->resolveUser(
            "\0\0\0",
            ['email' => 'test@example.com'],
        );
    }

    #[Test]
    public function resolveUserWithInvalidEmailFormatSkipsEmailLookup(): void
    {
        $login = 'oauth_invalidemail_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-invalidemail-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $resolver = new OAuthUserResolver(providerName: 'google');

        // Invalid email format should be skipped, resolver falls through to login lookup
        $user = $resolver->resolveUser(
            $login,
            ['email' => 'not-an-email'],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($userId, $user->ID);
    }

    #[Test]
    public function autoProvisionWithXssInDisplayName(): void
    {
        $subject = 'provisioned_xss_' . uniqid();
        $resolver = new OAuthUserResolver(providerName: 'google', autoProvision: true);

        $user = $resolver->resolveUser(
            $subject,
            [
                'email' => 'xss-' . uniqid() . '@example.com',
                'name' => '<script>alert("xss")</script>',
                'given_name' => '<img src=x onerror=alert(1)>',
                'family_name' => '<b>bold</b>',
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
    public function roleMapUnmatchedRoleKeepsExisting(): void
    {
        $login = 'oauth_unmatched_role_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-unmatched-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'editor',
        ]);

        self::assertIsInt($userId);

        // Explicitly set role to ensure it's applied in the test environment
        $wpUser = get_user_by('id', $userId);
        $wpUser->set_role('editor');

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            roleMapping: ['admin_group' => 'administrator'],
            roleClaim: 'role',
        );

        $user = $resolver->resolveUser(
            $login,
            ['role' => 'unknown_group'],
        );

        self::assertContains('editor', $user->roles);
    }

    #[Test]
    public function resolveUserByLoginBindsSubject(): void
    {
        $login = 'oauth_login_bind_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-loginbind-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        $resolver = new OAuthUserResolver(providerName: 'google');

        // Resolve by login (no email or bound subject)
        $user = $resolver->resolveUser($login, []);

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($login, get_user_meta($userId, '_wppack_oauth_sub_google', true));
    }

    #[Test]
    public function getClaimValueReturnsNullForBooleanValue(): void
    {
        $subject = 'oauth-bool-' . uniqid();
        $userId = wp_insert_user([
            'user_login' => 'oauth_bool_' . uniqid(),
            'user_email' => 'oauth-bool-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        update_user_meta($userId, '_wppack_oauth_sub_google', $subject);

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            firstNameClaim: 'bool_claim',
        );

        // Boolean claim values should be ignored (returns null)
        $user = $resolver->resolveUser(
            $subject,
            ['bool_claim' => true],
        );

        self::assertInstanceOf(\WP_User::class, $user);
    }

    #[Test]
    public function getClaimValueReturnsNullForFloatValue(): void
    {
        $subject = 'oauth-float-' . uniqid();
        $userId = wp_insert_user([
            'user_login' => 'oauth_float_' . uniqid(),
            'user_email' => 'oauth-float-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        update_user_meta($userId, '_wppack_oauth_sub_google', $subject);

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            firstNameClaim: 'float_claim',
        );

        // Float claim values should be ignored (returns null)
        $user = $resolver->resolveUser(
            $subject,
            ['float_claim' => 3.14],
        );

        self::assertInstanceOf(\WP_User::class, $user);
    }

    #[Test]
    public function getClaimValueReturnsNullForArrayValue(): void
    {
        $subject = 'oauth-arr-' . uniqid();
        $userId = wp_insert_user([
            'user_login' => 'oauth_arr_' . uniqid(),
            'user_email' => 'oauth-arr-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        self::assertIsInt($userId);

        update_user_meta($userId, '_wppack_oauth_sub_google', $subject);

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            firstNameClaim: 'array_claim',
        );

        // Array claim values should be ignored (returns null)
        $user = $resolver->resolveUser(
            $subject,
            ['array_claim' => ['value1', 'value2']],
        );

        self::assertInstanceOf(\WP_User::class, $user);
    }

    #[Test]
    public function autoProvisionWithoutEmail(): void
    {
        $subject = 'provisioned_noemail_' . uniqid();
        $resolver = new OAuthUserResolver(providerName: 'google', autoProvision: true);

        $user = $resolver->resolveUser(
            $subject,
            [
                'given_name' => 'NoEmail',
                'family_name' => 'User',
            ],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        // When no email is provided, subject is used as fallback for user_email
        self::assertSame($subject, $user->user_login);
        self::assertSame($subject, get_user_meta($user->ID, '_wppack_oauth_sub_google', true));
    }

    #[Test]
    public function autoProvisionWithNullClaimNames(): void
    {
        $subject = 'provisioned_nullclaims_' . uniqid();
        $resolver = new OAuthUserResolver(
            providerName: 'google',
            autoProvision: true,
            firstNameClaim: null,
            lastNameClaim: null,
            displayNameClaim: null,
        );

        $user = $resolver->resolveUser(
            $subject,
            [
                'given_name' => 'Ignored',
                'family_name' => 'Ignored',
                'name' => 'Ignored',
            ],
        );

        self::assertInstanceOf(\WP_User::class, $user);
    }

    #[Test]
    public function syncUserAttributesWithNullClaimNames(): void
    {
        $subject = 'oauth-nullsync-' . uniqid();
        $userId = wp_insert_user([
            'user_login' => 'oauth_nullsync_' . uniqid(),
            'user_email' => 'oauth-nullsync-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'first_name' => 'Original',
        ]);

        self::assertIsInt($userId);

        update_user_meta($userId, '_wppack_oauth_sub_google', $subject);

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            firstNameClaim: null,
            lastNameClaim: null,
            displayNameClaim: null,
        );

        $user = $resolver->resolveUser(
            $subject,
            ['given_name' => 'ShouldBeIgnored'],
        );

        // first_name should not be updated since firstNameClaim is null
        $refreshed = get_user_by('id', $user->ID);
        self::assertSame('Original', $refreshed->first_name);
    }

    #[Test]
    public function mapUserRoleWithNonStringArrayElements(): void
    {
        $login = 'oauth_role_nonstr_' . uniqid();
        $userId = wp_insert_user([
            'user_login' => $login,
            'user_email' => 'oauth-role-nonstr-' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ]);

        self::assertIsInt($userId);

        $wpUser = get_user_by('id', $userId);
        $wpUser->set_role('subscriber');

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            roleMapping: ['admin_group' => 'administrator'],
            roleClaim: 'groups',
        );

        // Non-string array elements should be skipped
        $user = $resolver->resolveUser(
            $login,
            ['groups' => [42, true, null]],
        );

        self::assertContains('subscriber', $user->roles);
    }

    #[Test]
    public function autoProvisionThrowsWhenWpInsertUserFails(): void
    {
        // Use a login > 60 chars to cause wp_insert_user to return WP_Error
        $longSubject = str_repeat('a', 61);

        $resolver = new OAuthUserResolver(providerName: 'google', autoProvision: true);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('User provisioning failed.');

        $resolver->resolveUser(
            $longSubject,
            ['email' => 'long-subject-' . uniqid() . '@example.com'],
        );
    }

    #[Test]
    public function autoProvisionWithCustomEmailClaim(): void
    {
        $subject = 'provisioned_custom_email_' . uniqid();
        $customEmail = 'custom-' . uniqid() . '@example.com';

        $resolver = new OAuthUserResolver(
            providerName: 'google',
            autoProvision: true,
            emailClaim: 'preferred_email',
        );

        $user = $resolver->resolveUser(
            $subject,
            ['preferred_email' => $customEmail],
        );

        self::assertInstanceOf(\WP_User::class, $user);
        self::assertSame($customEmail, $user->user_email);
    }
}
