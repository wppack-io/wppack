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

namespace WPPack\Plugin\RoleProvisioningPlugin\Tests\Provisioning;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\User\UserRepository;
use WPPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;
use WPPack\Plugin\RoleProvisioningPlugin\Provisioning\RoleProvisioner;

#[CoversClass(RoleProvisioner::class)]
final class RoleProvisionerTest extends TestCase
{
    /** @var list<int> */
    private array $createdUsers = [];

    protected function tearDown(): void
    {
        foreach ($this->createdUsers as $id) {
            wp_delete_user($id);
        }
        $this->createdUsers = [];
        remove_all_actions('user_register');
        remove_all_actions('updated_user_meta');
    }

    private function user(array $overrides = []): \WP_User
    {
        $unique = uniqid();
        $id = (int) wp_insert_user(array_merge([
            'user_login' => 'rp_' . $unique,
            'user_email' => 'rp_' . $unique . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ], $overrides));
        $this->createdUsers[] = $id;

        return new \WP_User($id);
    }

    private function provisioner(RoleProvisioningConfiguration $config): RoleProvisioner
    {
        return new RoleProvisioner(
            $config,
            new RoleProvider(),
            new BlogContext(),
            new UserRepository(),
            new NullLogger(),
        );
    }

    private function rule(array $conditions, string $role = 'editor', ?array $blogIds = null): array
    {
        return ['conditions' => $conditions, 'role' => $role, 'blogIds' => $blogIds];
    }

    // ── register() ──────────────────────────────────────────────────────

    #[Test]
    public function registerWiresUserRegisterHookWhenEnabled(): void
    {
        $this->provisioner(new RoleProvisioningConfiguration(enabled: true))->register();

        self::assertNotFalse(has_action('user_register'));
    }

    #[Test]
    public function registerNoOpsWhenDisabled(): void
    {
        $this->provisioner(new RoleProvisioningConfiguration(enabled: false))->register();

        self::assertFalse(has_action('user_register'));
    }

    #[Test]
    public function registerAddsSyncHookWhenSyncOnLoginEnabled(): void
    {
        $this->provisioner(new RoleProvisioningConfiguration(syncOnLogin: true))->register();

        self::assertNotFalse(has_action('updated_user_meta'));
    }

    // ── evaluateRules() ─────────────────────────────────────────────────

    #[Test]
    public function evaluateRulesReturnsFirstMatch(): void
    {
        $user = $this->user(['user_email' => 'someone@wppack.dev']);

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@never.example']], 'editor'),
            $this->rule([['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev']], 'author'),
        ]);

        $match = $this->provisioner($config)->evaluateRules($user->ID);

        self::assertNotNull($match);
        self::assertSame('author', $match['role']);
    }

    #[Test]
    public function evaluateRulesReturnsNullWhenNoRuleMatches(): void
    {
        $user = $this->user();

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'equals', 'value' => 'not-a-match@example.com']], 'editor'),
        ]);

        self::assertNull($this->provisioner($config)->evaluateRules($user->ID));
    }

    #[Test]
    public function evaluateRulesReturnsNullForNonExistentUser(): void
    {
        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'exists', 'value' => '']], 'editor'),
        ]);

        self::assertNull($this->provisioner($config)->evaluateRules(99999999));
    }

    #[Test]
    public function emptyConditionsNeverMatch(): void
    {
        $user = $this->user();

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([], 'editor'),
        ]);

        self::assertNull($this->provisioner($config)->evaluateRules($user->ID));
    }

    #[Test]
    public function allConditionsMustMatchForRuleToMatch(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev']);

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([
                ['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev'],
                ['field' => 'user.login', 'operator' => 'equals', 'value' => 'someone-else'],
            ], 'editor'),
        ]);

        self::assertNull($this->provisioner($config)->evaluateRules($user->ID), 'second condition fails');
    }

    // ── operator coverage ───────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, string, string, bool}>
     */
    public static function operatorCases(): iterable
    {
        yield 'equals-match' => ['equals', 'alice@wppack.dev', 'alice@wppack.dev', true];
        yield 'equals-miss' => ['equals', 'alice@wppack.dev', 'bob@wppack.dev', false];
        yield 'not_equals-match' => ['not_equals', 'alice@wppack.dev', 'bob@wppack.dev', true];
        yield 'not_equals-miss' => ['not_equals', 'alice@wppack.dev', 'alice@wppack.dev', false];
        yield 'contains-match' => ['contains', 'alice@wppack.dev', 'wppack', true];
        yield 'contains-miss' => ['contains', 'alice@wppack.dev', 'nope', false];
        yield 'starts_with-match' => ['starts_with', 'alice@wppack.dev', 'alice', true];
        yield 'ends_with-match' => ['ends_with', 'alice@wppack.dev', '.dev', true];
        yield 'matches-regex' => ['matches', 'alice@wppack.dev', '/^[a-z]+@[a-z.]+$/', true];
        yield 'exists-match' => ['exists', 'alice@wppack.dev', '', true];
        yield 'unknown-op' => ['not_an_operator', 'alice@wppack.dev', 'alice@wppack.dev', false];
    }

    #[Test]
    #[DataProvider('operatorCases')]
    public function operatorBehavesAsSpecified(string $operator, string $email, string $expected, bool $shouldMatch): void
    {
        $user = $this->user(['user_email' => $email]);

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => $operator, 'value' => $expected]], 'editor'),
        ]);

        $match = $this->provisioner($config)->evaluateRules($user->ID);

        if ($shouldMatch) {
            self::assertNotNull($match, "{$operator} should match");
        } else {
            self::assertNull($match, "{$operator} should not match");
        }
    }

    // ── field resolution ────────────────────────────────────────────────

    #[Test]
    public function userFieldsAreResolvable(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev', 'display_name' => 'Alice']);

        foreach (['email', 'display_name', 'login', 'nicename'] as $field) {
            $config = new RoleProvisioningConfiguration(rules: [
                $this->rule([['field' => 'user.' . $field, 'operator' => 'exists', 'value' => '']], 'editor'),
            ]);

            self::assertNotNull(
                $this->provisioner($config)->evaluateRules($user->ID),
                "user.{$field} resolvable",
            );
        }
    }

    #[Test]
    public function metaFieldsResolveDirectValue(): void
    {
        $user = $this->user();
        update_user_meta($user->ID, 'department', 'engineering');

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'meta.department', 'operator' => 'equals', 'value' => 'engineering']], 'editor'),
        ]);

        self::assertNotNull($this->provisioner($config)->evaluateRules($user->ID));
    }

    #[Test]
    public function metaFieldsResolveJsonDotPath(): void
    {
        $user = $this->user();
        update_user_meta($user->ID, 'saml_attrs', json_encode([
            'groups' => ['admin', 'dev'],
            'location' => ['country' => 'JP'],
        ]));

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'meta.saml_attrs.groups.0', 'operator' => 'equals', 'value' => 'admin']], 'editor'),
        ]);

        self::assertNotNull($this->provisioner($config)->evaluateRules($user->ID));
    }

    #[Test]
    public function metaDotPathReturnsEmptyForMissingSegment(): void
    {
        $user = $this->user();
        update_user_meta($user->ID, 'attrs', json_encode(['a' => 'x']));

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'meta.attrs.nope', 'operator' => 'exists', 'value' => '']], 'editor'),
        ]);

        self::assertNull($this->provisioner($config)->evaluateRules($user->ID));
    }

    #[Test]
    public function unknownFieldPrefixYieldsEmpty(): void
    {
        $user = $this->user();

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'weird.thing', 'operator' => 'exists', 'value' => '']], 'editor'),
        ]);

        self::assertNull($this->provisioner($config)->evaluateRules($user->ID));
    }

    // ── provision() semantics ───────────────────────────────────────────

    #[Test]
    public function provisionAssignsMatchedRoleAndRecordsMeta(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev', 'role' => 'subscriber']);

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev']], 'editor'),
        ]);

        $this->provisioner($config)->provision($user->ID);

        $fresh = new \WP_User($user->ID);
        self::assertContains('editor', $fresh->roles);
        self::assertSame('editor', get_user_meta($user->ID, '_wppack_provisioned_role', true));
    }

    #[Test]
    public function provisionSkipsProtectedRoles(): void
    {
        $user = $this->user(['role' => 'administrator']);

        $config = new RoleProvisioningConfiguration(
            protectedRoles: ['administrator'],
            rules: [
                $this->rule([['field' => 'user.email', 'operator' => 'exists', 'value' => '']], 'subscriber'),
            ],
        );

        $this->provisioner($config)->provision($user->ID);

        $fresh = new \WP_User($user->ID);
        self::assertContains('administrator', $fresh->roles);
    }

    #[Test]
    public function syncDoesNothingWhenNoProvisionedRoleMetaExists(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev', 'role' => 'subscriber']);

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev']], 'editor'),
        ]);

        // No _wppack_provisioned_role meta → sync must be a no-op
        $this->provisioner($config)->provision($user->ID, isSync: true);

        $fresh = new \WP_User($user->ID);
        self::assertContains('subscriber', $fresh->roles, 'role unchanged on initial sync call');
    }

    #[Test]
    public function syncRespectsManualRoleChange(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev', 'role' => 'subscriber']);
        update_user_meta($user->ID, '_wppack_provisioned_role', 'editor');

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev']], 'author'),
        ]);

        // Current role ('subscriber') differs from recorded provisioned role ('editor') — manual change detected
        $this->provisioner($config)->provision($user->ID, isSync: true);

        $fresh = new \WP_User($user->ID);
        self::assertContains('subscriber', $fresh->roles);
    }

    #[Test]
    public function syncAppliesMatchedRoleWhenRoleMatchesPreviousProvision(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev', 'role' => 'editor']);
        update_user_meta($user->ID, '_wppack_provisioned_role', 'editor');

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev']], 'author'),
        ]);

        $this->provisioner($config)->provision($user->ID, isSync: true);

        $fresh = new \WP_User($user->ID);
        self::assertContains('author', $fresh->roles);
    }

    #[Test]
    public function provisionFallsBackToDefaultRoleWhenMatchedRoleIsUnknown(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev', 'role' => 'subscriber']);

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev']], 'nonexistent-role'),
        ]);

        $this->provisioner($config)->provision($user->ID);

        $fresh = new \WP_User($user->ID);
        self::assertContains(get_option('default_role', 'subscriber'), $fresh->roles);
    }

    #[Test]
    public function roleTemplatePlaceholderIsSubstituted(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev']);
        update_user_meta($user->ID, 'department', 'editor');

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule(
                [['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev']],
                '{{meta.department}}',
            ),
        ]);

        $this->provisioner($config)->provision($user->ID);

        $fresh = new \WP_User($user->ID);
        self::assertContains('editor', $fresh->roles, 'placeholder resolved to editor');
    }

    #[Test]
    public function onUserRegisterInvokesProvision(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev', 'role' => 'subscriber']);

        $config = new RoleProvisioningConfiguration(rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev']], 'editor'),
        ]);

        $this->provisioner($config)->onUserRegister($user->ID);

        $fresh = new \WP_User($user->ID);
        self::assertContains('editor', $fresh->roles);
    }

    // ── onMetaUpdated SSO filter ────────────────────────────────────────

    #[Test]
    public function onMetaUpdatedOnlyTriggersForSsoMetaKeys(): void
    {
        $user = $this->user(['user_email' => 'alice@wppack.dev', 'role' => 'editor']);
        update_user_meta($user->ID, '_wppack_provisioned_role', 'editor');

        $config = new RoleProvisioningConfiguration(syncOnLogin: true, rules: [
            $this->rule([['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@wppack.dev']], 'author'),
        ]);

        $provisioner = $this->provisioner($config);

        // Unrelated meta key → no change
        $provisioner->onMetaUpdated(1, $user->ID, 'unrelated_meta', 'v');
        self::assertContains('editor', (new \WP_User($user->ID))->roles);

        // SSO meta key → re-evaluated
        $provisioner->onMetaUpdated(1, $user->ID, '_wppack_saml_attributes', 'v');
        self::assertContains('author', (new \WP_User($user->ID))->roles);
    }
}
