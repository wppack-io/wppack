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

namespace WPPack\Component\Scim\Tests\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Mapping\GroupMapper;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Serialization\ScimGroupSerializer;

#[CoversClass(ScimGroupSerializer::class)]
final class ScimGroupSerializerTest extends TestCase
{
    private function createUser(string $prefix, string $displayName): \WP_User
    {
        $userId = (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
            'display_name' => $displayName,
        ]);

        return new \WP_User($userId);
    }

    #[Test]
    public function serializesGroupWithScimSchemaAndMetaLocation(): void
    {
        $serializer = new ScimGroupSerializer(new GroupMapper());

        $result = $serializer->serialize(
            'editor',
            ['name' => 'Editor', 'capabilities' => ['edit_posts' => true]],
            members: [],
            baseUrl: 'https://example.test',
        );

        self::assertSame([ScimConstants::GROUP_SCHEMA], $result['schemas']);
        self::assertSame('editor', $result['id']);
        self::assertSame('Editor', $result['displayName']);
        self::assertSame([], $result['members']);
        self::assertSame('Group', $result['meta']['resourceType']);
        self::assertSame(
            'https://example.test/scim/v2/Groups/editor',
            $result['meta']['location'],
        );
    }

    #[Test]
    public function serializesMembersWithValueDisplayAndRef(): void
    {
        $serializer = new ScimGroupSerializer(new GroupMapper());
        $alice = $this->createUser('alice', 'Alice');

        $result = $serializer->serialize(
            'editor',
            ['name' => 'Editor', 'capabilities' => []],
            [$alice],
            'https://example.test',
        );

        self::assertCount(1, $result['members']);
        self::assertSame((string) $alice->ID, $result['members'][0]['value']);
        self::assertSame('Alice', $result['members'][0]['display']);
        self::assertSame(
            'https://example.test/scim/v2/Users/' . $alice->ID,
            $result['members'][0]['$ref'],
        );
    }

    #[Test]
    public function defaultBaseUrlProducesRelativeLocation(): void
    {
        $serializer = new ScimGroupSerializer(new GroupMapper());

        $result = $serializer->serialize('subscriber', ['name' => 'Subscriber', 'capabilities' => []], []);

        self::assertSame('/scim/v2/Groups/subscriber', $result['meta']['location']);
    }
}
