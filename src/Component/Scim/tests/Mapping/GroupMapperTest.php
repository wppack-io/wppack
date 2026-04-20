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

namespace WPPack\Component\Scim\Tests\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Mapping\GroupMapper;

#[CoversClass(GroupMapper::class)]
final class GroupMapperTest extends TestCase
{
    private function createUser(string $prefix): \WP_User
    {
        $userId = (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
            'display_name' => ucfirst($prefix),
        ]);

        return new \WP_User($userId);
    }

    #[Test]
    public function emitsScimGroupWithZeroMembers(): void
    {
        $mapper = new GroupMapper();

        $result = $mapper->toScim(
            'subscriber',
            ['name' => 'Subscriber', 'capabilities' => ['read' => true]],
            members: [],
        );

        self::assertSame('subscriber', $result['id']);
        self::assertSame('Subscriber', $result['displayName']);
        self::assertSame([], $result['members']);
    }

    #[Test]
    public function emitsScimGroupWithMembersIncludingRefs(): void
    {
        $mapper = new GroupMapper();
        $alice = $this->createUser('alice');
        $bob = $this->createUser('bob');

        $result = $mapper->toScim(
            'editor',
            ['name' => 'Editor', 'capabilities' => []],
            [$alice, $bob],
            baseUrl: 'https://example.test',
        );

        self::assertSame('editor', $result['id']);
        self::assertSame('Editor', $result['displayName']);
        self::assertCount(2, $result['members']);

        self::assertSame((string) $alice->ID, $result['members'][0]['value']);
        self::assertSame('Alice', $result['members'][0]['display']);
        self::assertSame(
            'https://example.test/scim/v2/Users/' . $alice->ID,
            $result['members'][0]['$ref'],
        );

        self::assertSame((string) $bob->ID, $result['members'][1]['value']);
    }

    #[Test]
    public function defaultBaseUrlEmitsRelativeRef(): void
    {
        $mapper = new GroupMapper();
        $user = $this->createUser('admin');

        $result = $mapper->toScim('administrator', ['name' => 'Administrator', 'capabilities' => []], [$user]);

        self::assertStringStartsWith('/scim/v2/Users/', $result['members'][0]['$ref']);
    }
}
