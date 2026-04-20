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

namespace WPPack\Plugin\RoleProvisioningPlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;

#[CoversClass(RoleProvisioningConfiguration::class)]
final class RoleProvisioningConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        delete_option(RoleProvisioningConfiguration::OPTION_NAME);
    }

    protected function tearDown(): void
    {
        delete_option(RoleProvisioningConfiguration::OPTION_NAME);
    }

    #[Test]
    public function defaultsMatchSafeInitialState(): void
    {
        $config = new RoleProvisioningConfiguration();

        self::assertTrue($config->enabled);
        self::assertFalse($config->addUserToBlog);
        self::assertFalse($config->syncOnLogin);
        self::assertSame(['administrator'], $config->protectedRoles);
        self::assertSame([], $config->rules);
    }

    #[Test]
    public function fromOptionReturnsDefaultsForMissingOption(): void
    {
        $config = RoleProvisioningConfiguration::fromOption();

        self::assertTrue($config->enabled);
        self::assertSame(['administrator'], $config->protectedRoles);
    }

    #[Test]
    public function fromOptionHonoursSavedFields(): void
    {
        update_option(RoleProvisioningConfiguration::OPTION_NAME, [
            'enabled' => false,
            'addUserToBlog' => true,
            'syncOnLogin' => true,
            'protectedRoles' => ['administrator', 'editor'],
        ]);

        $config = RoleProvisioningConfiguration::fromOption();

        self::assertFalse($config->enabled);
        self::assertTrue($config->addUserToBlog);
        self::assertTrue($config->syncOnLogin);
        self::assertSame(['administrator', 'editor'], $config->protectedRoles);
    }

    #[Test]
    public function fromOptionFiltersNonStringProtectedRoles(): void
    {
        update_option(RoleProvisioningConfiguration::OPTION_NAME, [
            'protectedRoles' => ['administrator', 123, null, 'editor'],
        ]);

        $config = RoleProvisioningConfiguration::fromOption();

        self::assertSame(['administrator', 'editor'], $config->protectedRoles);
    }

    #[Test]
    public function fromOptionNormalisesRules(): void
    {
        update_option(RoleProvisioningConfiguration::OPTION_NAME, [
            'rules' => [
                [
                    'conditions' => [
                        ['field' => 'user.email', 'operator' => 'ends_with', 'value' => '@example.com'],
                    ],
                    'role' => 'editor',
                    'blogIds' => ['1', '2', '3'],
                ],
            ],
        ]);

        $config = RoleProvisioningConfiguration::fromOption();

        self::assertCount(1, $config->rules);
        $rule = $config->rules[0];
        self::assertSame('editor', $rule['role']);
        self::assertSame([1, 2, 3], $rule['blogIds']);
        self::assertSame([
            'field' => 'user.email',
            'operator' => 'ends_with',
            'value' => '@example.com',
        ], $rule['conditions'][0]);
    }

    #[Test]
    public function fromOptionUsesEqualsAsDefaultOperator(): void
    {
        update_option(RoleProvisioningConfiguration::OPTION_NAME, [
            'rules' => [
                [
                    'conditions' => [['field' => 'user.email', 'value' => '@a.com']],
                    'role' => 'editor',
                ],
            ],
        ]);

        $config = RoleProvisioningConfiguration::fromOption();

        self::assertSame('equals', $config->rules[0]['conditions'][0]['operator']);
        self::assertNull($config->rules[0]['blogIds']);
    }

    #[Test]
    public function fromOptionDiscardsNonArrayRuleEntries(): void
    {
        update_option(RoleProvisioningConfiguration::OPTION_NAME, [
            'rules' => [
                'not an array',
                ['conditions' => [], 'role' => 'editor'],
            ],
        ]);

        $config = RoleProvisioningConfiguration::fromOption();

        self::assertCount(1, $config->rules, 'string entries silently dropped');
    }

    #[Test]
    public function fromOptionHandlesNonArrayOptionValue(): void
    {
        update_option(RoleProvisioningConfiguration::OPTION_NAME, 'garbage');

        $config = RoleProvisioningConfiguration::fromOption();

        self::assertTrue($config->enabled, 'defaults survive corrupted option');
    }
}
