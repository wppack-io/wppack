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

namespace WPPack\Component\Scim\Tests\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Schema\GroupSchema;
use WPPack\Component\Scim\Schema\ResourceTypeDefinition;
use WPPack\Component\Scim\Schema\SchemaDefinition;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Schema\ServiceProviderConfig;
use WPPack\Component\Scim\Schema\UserSchema;

#[CoversClass(UserSchema::class)]
#[CoversClass(GroupSchema::class)]
#[CoversClass(SchemaDefinition::class)]
#[CoversClass(ResourceTypeDefinition::class)]
#[CoversClass(ServiceProviderConfig::class)]
#[CoversClass(ScimConstants::class)]
final class ScimSchemaTest extends TestCase
{
    // ── UserSchema ───────────────────────────────────────────────────

    #[Test]
    public function userSchemaDefinitionCarriesCoreIdentity(): void
    {
        $def = UserSchema::definition();

        self::assertSame(ScimConstants::USER_SCHEMA, $def->id);
        self::assertSame('User', $def->name);
        self::assertNotEmpty($def->attributes);
    }

    #[Test]
    public function userSchemaDefinesRequiredRfc7643Attributes(): void
    {
        $def = UserSchema::definition();
        $names = array_column($def->attributes, 'name');

        // Spot-check the core SCIM 2.0 user attributes.
        foreach (['userName', 'name', 'displayName', 'emails', 'active'] as $required) {
            self::assertContains($required, $names, "UserSchema is missing '{$required}'");
        }
    }

    #[Test]
    public function userResourceTypeExposesUsersEndpoint(): void
    {
        $rt = UserSchema::resourceType();

        self::assertSame('User', $rt->id);
        self::assertSame('/Users', $rt->endpoint);
        self::assertSame(ScimConstants::USER_SCHEMA, $rt->schema);
    }

    // ── GroupSchema ──────────────────────────────────────────────────

    #[Test]
    public function groupSchemaRequiresDisplayNameAndListsMembers(): void
    {
        $def = GroupSchema::definition();
        $byName = [];
        foreach ($def->attributes as $attr) {
            $byName[$attr['name']] = $attr;
        }

        self::assertArrayHasKey('displayName', $byName);
        self::assertTrue($byName['displayName']['required']);
        self::assertArrayHasKey('members', $byName);
        self::assertTrue($byName['members']['multiValued']);
    }

    #[Test]
    public function groupResourceTypeExposesGroupsEndpoint(): void
    {
        $rt = GroupSchema::resourceType();

        self::assertSame('Group', $rt->id);
        self::assertSame('/Groups', $rt->endpoint);
        self::assertSame(ScimConstants::GROUP_SCHEMA, $rt->schema);
    }

    // ── SchemaDefinition::toArray ────────────────────────────────────

    #[Test]
    public function schemaDefinitionSerialisationEmbedsMetaLocation(): void
    {
        $def = UserSchema::definition();

        $array = $def->toArray('https://example.test');

        self::assertSame([ScimConstants::SCHEMA_SCHEMA], $array['schemas']);
        self::assertSame($def->id, $array['id']);
        self::assertSame('User', $array['name']);
        self::assertSame($def->attributes, $array['attributes']);
        self::assertSame(
            'https://example.test/scim/v2/Schemas/' . $def->id,
            $array['meta']['location'],
        );
    }

    #[Test]
    public function schemaDefinitionDefaultsBaseUrlToEmpty(): void
    {
        $array = UserSchema::definition()->toArray();

        self::assertStringStartsWith('/scim/v2/Schemas/', $array['meta']['location']);
    }

    // ── ResourceTypeDefinition::toArray ──────────────────────────────

    #[Test]
    public function resourceTypeDefinitionSerialisation(): void
    {
        $rt = new ResourceTypeDefinition(
            id: 'User',
            name: 'User',
            description: 'User Account',
            endpoint: '/Users',
            schema: ScimConstants::USER_SCHEMA,
            schemaExtensions: ['urn:extension:foo'],
        );

        $array = $rt->toArray('https://example.test');

        self::assertSame([ScimConstants::RESOURCE_TYPE_SCHEMA], $array['schemas']);
        self::assertSame('User', $array['id']);
        self::assertSame('/Users', $array['endpoint']);
        self::assertSame(['urn:extension:foo'], $array['schemaExtensions']);
        self::assertSame(
            'https://example.test/scim/v2/ResourceTypes/User',
            $array['meta']['location'],
        );
    }

    #[Test]
    public function resourceTypeDefinitionDefaultsSchemaExtensionsToEmpty(): void
    {
        $rt = new ResourceTypeDefinition(
            id: 'Group',
            name: 'Group',
            description: '',
            endpoint: '/Groups',
            schema: ScimConstants::GROUP_SCHEMA,
        );

        self::assertSame([], $rt->schemaExtensions);
        self::assertSame([], $rt->toArray()['schemaExtensions']);
    }

    // ── ServiceProviderConfig ────────────────────────────────────────

    #[Test]
    public function serviceProviderConfigDefaults(): void
    {
        $cfg = new ServiceProviderConfig();

        self::assertSame(100, $cfg->maxResults);
        self::assertTrue($cfg->patchSupported);
        self::assertTrue($cfg->filterSupported);
        self::assertFalse($cfg->bulkSupported);
        self::assertFalse($cfg->changePasswordSupported);
        self::assertFalse($cfg->sortSupported);
        self::assertFalse($cfg->etagSupported);
        self::assertSame(200, $cfg->filterMaxResults);
    }

    #[Test]
    public function serviceProviderConfigSerialisationShape(): void
    {
        $cfg = new ServiceProviderConfig(
            patchSupported: true,
            filterSupported: true,
            filterMaxResults: 500,
        );

        $array = $cfg->toArray('https://example.test');

        self::assertSame([ScimConstants::SERVICE_PROVIDER_CONFIG_SCHEMA], $array['schemas']);
        self::assertSame(ScimConstants::RFC7644_URI, $array['documentationUri']);
        self::assertTrue($array['patch']['supported']);
        self::assertTrue($array['filter']['supported']);
        self::assertSame(500, $array['filter']['maxResults']);
        self::assertFalse($array['bulk']['supported']);
        self::assertFalse($array['changePassword']['supported']);
        self::assertFalse($array['sort']['supported']);
        self::assertFalse($array['etag']['supported']);
        self::assertSame('oauthbearertoken', $array['authenticationSchemes'][0]['type']);
        self::assertSame(ScimConstants::RFC6750_URI, $array['authenticationSchemes'][0]['specUri']);
        self::assertSame('ServiceProviderConfig', $array['meta']['resourceType']);
        self::assertSame(
            'https://example.test/scim/v2/ServiceProviderConfig',
            $array['meta']['location'],
        );
    }

    // ── ScimConstants ────────────────────────────────────────────────

    #[Test]
    public function scimConstantsMatchRfcSchemaUris(): void
    {
        self::assertSame('urn:ietf:params:scim:schemas:core:2.0:User', ScimConstants::USER_SCHEMA);
        self::assertSame('urn:ietf:params:scim:schemas:core:2.0:Group', ScimConstants::GROUP_SCHEMA);
        self::assertSame('urn:ietf:params:scim:api:messages:2.0:Error', ScimConstants::ERROR_SCHEMA);
        self::assertSame('urn:ietf:params:scim:api:messages:2.0:ListResponse', ScimConstants::LIST_RESPONSE_SCHEMA);
        self::assertSame('urn:ietf:params:scim:api:messages:2.0:PatchOp', ScimConstants::PATCH_OP_SCHEMA);
        self::assertSame('application/scim+json', ScimConstants::CONTENT_TYPE);
    }

    #[Test]
    public function scimConstantsMetaKeyPrefixStaysNamespaced(): void
    {
        // Any key we write to wp_usermeta must be under the wppack_scim_*
        // namespace so there's no collision with native WordPress / plugin
        // keys.
        self::assertStringStartsWith('_wppack_scim_', ScimConstants::META_EXTERNAL_ID);
        self::assertStringStartsWith('_wppack_scim_', ScimConstants::META_ACTIVE);
        self::assertStringStartsWith('_wppack_scim_', ScimConstants::META_TIMEZONE);
        self::assertStringStartsWith('_wppack_scim_', ScimConstants::META_TITLE);
        self::assertStringStartsWith('_wppack_scim_', ScimConstants::META_LAST_MODIFIED);
        self::assertStringStartsWith('_wppack_scim_group_', ScimConstants::META_GROUP_PREFIX);
    }
}
