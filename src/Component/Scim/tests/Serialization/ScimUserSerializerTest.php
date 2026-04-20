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
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Scim\Mapping\UserAttributeMapperInterface;
use WPPack\Component\Scim\Repository\ScimGroupRepository;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Serialization\ScimUserSerializer;

#[CoversClass(ScimUserSerializer::class)]
final class ScimUserSerializerTest extends TestCase
{
    private function createUser(string $prefix, string $registered = '2024-01-15 12:34:56'): \WP_User
    {
        $userId = (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
            'display_name' => 'Test User',
            'user_registered' => $registered,
        ]);

        return new \WP_User($userId);
    }

    /**
     * @param array<string, mixed> $scimAttributes
     * @param list<string> $groupNames
     */
    private function serializer(array $scimAttributes, array $groupNames = []): ScimUserSerializer
    {
        $mapper = $this->createMock(UserAttributeMapperInterface::class);
        $mapper->method('toScim')->willReturn($scimAttributes);

        $groups = $this->createMock(ScimGroupRepository::class);
        $groups->method('getGroupNamesForUser')->willReturn($groupNames);

        $roles = $this->createMock(RoleProvider::class);
        $roles->method('getNames')->willReturn([
            'administrator' => 'Administrator',
            'editor' => 'Editor',
        ]);

        return new ScimUserSerializer($mapper, $roles, $groups);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseAttributes(string $userName): array
    {
        return [
            'externalId' => null,
            'userName' => $userName,
            'name' => ['givenName' => 'Test', 'familyName' => 'User'],
            'displayName' => 'Test User',
            'nickName' => '',
            'profileUrl' => '',
            'emails' => [['value' => 'a@example.com', 'type' => 'work', 'primary' => true]],
            'active' => true,
            'locale' => null,
            'timezone' => null,
            'title' => null,
            'lastModified' => null,
        ];
    }

    #[Test]
    public function emitsCoreScimUserShape(): void
    {
        $user = $this->createUser('scim_core');
        $serializer = $this->serializer($this->baseAttributes($user->user_login));

        $result = $serializer->serialize($user, 'https://example.test');

        self::assertSame([ScimConstants::USER_SCHEMA], $result['schemas']);
        self::assertSame((string) $user->ID, $result['id']);
        self::assertSame($user->user_login, $result['userName']);
        self::assertSame('Test User', $result['displayName']);
        self::assertSame('User', $result['meta']['resourceType']);
        self::assertSame(
            'https://example.test/scim/v2/Users/' . $user->ID,
            $result['meta']['location'],
        );
    }

    #[Test]
    public function omitsExternalIdWhenNull(): void
    {
        $user = $this->createUser('scim_extid_missing');
        $serializer = $this->serializer($this->baseAttributes($user->user_login));

        $result = $serializer->serialize($user);

        self::assertArrayNotHasKey('externalId', $result);
    }

    #[Test]
    public function includesExternalIdWhenPresent(): void
    {
        $user = $this->createUser('scim_extid');
        $attrs = $this->baseAttributes($user->user_login);
        $attrs['externalId'] = 'okta-123';

        $serializer = $this->serializer($attrs);

        self::assertSame('okta-123', $serializer->serialize($user)['externalId']);
    }

    #[Test]
    public function omitsEmptyNickNameAndProfileUrl(): void
    {
        $user = $this->createUser('scim_sparse');
        $serializer = $this->serializer($this->baseAttributes($user->user_login));

        $result = $serializer->serialize($user);

        self::assertArrayNotHasKey('nickName', $result);
        self::assertArrayNotHasKey('profileUrl', $result);
    }

    #[Test]
    public function includesNickNameAndProfileUrlWhenSet(): void
    {
        $user = $this->createUser('scim_full');
        $attrs = $this->baseAttributes($user->user_login);
        $attrs['nickName'] = 'tester';
        $attrs['profileUrl'] = 'https://example.test/@tester';

        $result = $this->serializer($attrs)->serialize($user);

        self::assertSame('tester', $result['nickName']);
        self::assertSame('https://example.test/@tester', $result['profileUrl']);
    }

    #[Test]
    public function omitsNullLocaleTimezoneTitle(): void
    {
        $user = $this->createUser('scim_no_locale');
        $result = $this->serializer($this->baseAttributes($user->user_login))->serialize($user);

        self::assertArrayNotHasKey('locale', $result);
        self::assertArrayNotHasKey('timezone', $result);
        self::assertArrayNotHasKey('title', $result);
    }

    #[Test]
    public function includesLocaleTimezoneTitleWhenSet(): void
    {
        $user = $this->createUser('scim_locale');
        $attrs = $this->baseAttributes($user->user_login);
        $attrs['locale'] = 'en-US';
        $attrs['timezone'] = 'America/New_York';
        $attrs['title'] = 'Engineer';

        $result = $this->serializer($attrs)->serialize($user);

        self::assertSame('en-US', $result['locale']);
        self::assertSame('America/New_York', $result['timezone']);
        self::assertSame('Engineer', $result['title']);
    }

    #[Test]
    public function mergesRoleLabelIntoGroupsViaRoleProvider(): void
    {
        $user = $this->createUser('scim_groups');
        $serializer = $this->serializer(
            $this->baseAttributes($user->user_login),
            groupNames: ['administrator', 'editor', 'unknown-role'],
        );

        $result = $serializer->serialize($user, 'https://example.test');

        self::assertCount(3, $result['groups']);
        self::assertSame('administrator', $result['groups'][0]['value']);
        self::assertSame('Administrator', $result['groups'][0]['display']);
        self::assertSame(
            'https://example.test/scim/v2/Groups/administrator',
            $result['groups'][0]['$ref'],
        );
        self::assertSame('Editor', $result['groups'][1]['display']);
        // Unknown role falls back to its name so SCIM clients still see
        // the membership — better a readable fallback than an empty label.
        self::assertSame('unknown-role', $result['groups'][2]['display']);
    }

    #[Test]
    public function emptyGroupsArrayWhenUserHasNoSyncedRoles(): void
    {
        $user = $this->createUser('scim_no_groups');
        $result = $this->serializer($this->baseAttributes($user->user_login))->serialize($user);

        self::assertSame([], $result['groups']);
    }

    #[Test]
    public function metaCreatedIsUserRegisteredInIso8601(): void
    {
        $user = $this->createUser('scim_created', registered: '2023-06-15 12:00:00');
        $result = $this->serializer($this->baseAttributes($user->user_login))->serialize($user);

        // 'Y-m-d H:i:s' in UTC → ISO 8601 with explicit offset
        self::assertSame('2023-06-15T12:00:00+00:00', $result['meta']['created']);
    }

    #[Test]
    public function metaLastModifiedFallsBackToUserRegisteredWhenUnset(): void
    {
        $user = $this->createUser('scim_lastmod_default', registered: '2023-06-15 12:00:00');
        $result = $this->serializer($this->baseAttributes($user->user_login))->serialize($user);

        self::assertSame('2023-06-15T12:00:00+00:00', $result['meta']['lastModified']);
    }

    #[Test]
    public function metaLastModifiedUsesAttributeValueWhenSet(): void
    {
        $user = $this->createUser('scim_lastmod_override');
        $attrs = $this->baseAttributes($user->user_login);
        $attrs['lastModified'] = '2024-06-01T12:00:00+00:00';

        $result = $this->serializer($attrs)->serialize($user);

        self::assertSame('2024-06-01T12:00:00+00:00', $result['meta']['lastModified']);
    }

    #[Test]
    public function malformedUserRegisteredFallsBackToNow(): void
    {
        $user = $this->createUser('scim_bad_date');
        // Simulate a corrupted value (shouldn't normally happen in WP).
        $user->user_registered = 'not-a-date';

        $result = $this->serializer($this->baseAttributes($user->user_login))->serialize($user);

        // Can't assert the exact timestamp but must be a valid ISO 8601
        // UTC string — toIso8601 falls back to "now".
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            $result['meta']['created'],
        );
    }

    #[Test]
    public function defaultBaseUrlProducesRelativeLocation(): void
    {
        $user = $this->createUser('scim_rel');
        $result = $this->serializer($this->baseAttributes($user->user_login))->serialize($user);

        self::assertSame('/scim/v2/Users/' . $user->ID, $result['meta']['location']);
    }
}
