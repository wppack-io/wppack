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

namespace WPPack\Component\Role\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Role\RoleProvider;

#[CoversClass(RoleProvider::class)]
final class RoleProviderTest extends TestCase
{
    /** @var list<string> */
    private array $createdRoles = [];

    protected function tearDown(): void
    {
        foreach ($this->createdRoles as $name) {
            remove_role($name);
        }
        $this->createdRoles = [];
    }

    #[Test]
    public function findReturnsStandardWordPressRole(): void
    {
        $role = (new RoleProvider())->find('administrator');

        self::assertIsArray($role);
        self::assertArrayHasKey('name', $role);
        self::assertArrayHasKey('capabilities', $role);
        self::assertArrayHasKey('manage_options', $role['capabilities']);
    }

    #[Test]
    public function findReturnsNullForUnknownRole(): void
    {
        self::assertNull((new RoleProvider())->find('nonexistent-role-' . uniqid()));
    }

    #[Test]
    public function allReturnsAllRoles(): void
    {
        $all = (new RoleProvider())->all();

        self::assertIsArray($all);
        self::assertArrayHasKey('administrator', $all);
        self::assertArrayHasKey('subscriber', $all);
    }

    #[Test]
    public function getNamesReturnsDisplayLabels(): void
    {
        $names = (new RoleProvider())->getNames();

        self::assertArrayHasKey('administrator', $names);
        self::assertIsString($names['administrator']);
        self::assertNotSame('', $names['administrator']);
    }

    #[Test]
    public function addCreatesNewRoleRetrievableViaFind(): void
    {
        $provider = new RoleProvider();
        $name = 'wppack_test_role_' . uniqid();
        $this->createdRoles[] = $name;

        $provider->add($name, 'Test Label', ['read' => true]);

        $role = $provider->find($name);
        self::assertNotNull($role);
        self::assertSame('Test Label', $role['name']);
        self::assertTrue($role['capabilities']['read']);
    }

    #[Test]
    public function removeDeletesRole(): void
    {
        $provider = new RoleProvider();
        $name = 'wppack_test_remove_' . uniqid();

        $provider->add($name, 'Temp', []);
        self::assertNotNull($provider->find($name));

        $provider->remove($name);

        self::assertNull($provider->find($name));
    }

    #[Test]
    public function updateLabelChangesDisplayName(): void
    {
        $provider = new RoleProvider();
        $name = 'wppack_test_label_' . uniqid();
        $this->createdRoles[] = $name;

        $provider->add($name, 'Original', []);
        $provider->updateLabel($name, 'Updated Label');

        $role = $provider->find($name);
        self::assertNotNull($role);
        self::assertSame('Updated Label', $role['name']);
    }

    #[Test]
    public function updateLabelSilentlyIgnoresUnknownRole(): void
    {
        $provider = new RoleProvider();

        // No exception
        $this->expectNotToPerformAssertions();
        $provider->updateLabel('nonexistent-' . uniqid(), 'New Label');
    }

    #[Test]
    public function addCapabilityAndRemoveCapabilityRoundTrip(): void
    {
        $provider = new RoleProvider();
        $name = 'wppack_test_cap_' . uniqid();
        $this->createdRoles[] = $name;

        $provider->add($name, 'Cap', []);
        $provider->addCapability($name, 'custom_capability');

        $role = $provider->find($name);
        self::assertNotNull($role);
        self::assertTrue($role['capabilities']['custom_capability']);

        $provider->removeCapability($name, 'custom_capability');

        $role = $provider->find($name);
        self::assertNotNull($role);
        self::assertArrayNotHasKey('custom_capability', $role['capabilities']);
    }

    #[Test]
    public function capabilityMutationsNoOpForUnknownRoles(): void
    {
        $provider = new RoleProvider();

        $provider->addCapability('no-such-role-' . uniqid(), 'read');
        $provider->removeCapability('no-such-role-' . uniqid(), 'read');

        self::assertTrue(true, 'no exception from unknown role mutations');
    }
}
