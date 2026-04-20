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
use WPPack\Component\Scim\Schema\ResourceTypeDefinition;
use WPPack\Component\Scim\Schema\SchemaDefinition;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Schema\ServiceProviderConfig;

#[CoversClass(ServiceProviderConfig::class)]
#[CoversClass(ResourceTypeDefinition::class)]
#[CoversClass(SchemaDefinition::class)]
#[CoversClass(ScimConstants::class)]
final class SchemaDtosTest extends TestCase
{
    #[Test]
    public function serviceProviderConfigDefaultsMatchRfc7644Minimum(): void
    {
        $config = new ServiceProviderConfig();

        self::assertSame(100, $config->maxResults);
        self::assertFalse($config->changePasswordSupported);
        self::assertTrue($config->patchSupported, 'PATCH is a core SCIM capability');
        self::assertFalse($config->bulkSupported);
        self::assertTrue($config->filterSupported);
        self::assertSame(200, $config->filterMaxResults);
        self::assertFalse($config->sortSupported);
        self::assertFalse($config->etagSupported);
    }

    #[Test]
    public function serviceProviderConfigToArrayEmitsAllRequiredSections(): void
    {
        $config = new ServiceProviderConfig();
        $payload = $config->toArray('https://example.com');

        self::assertSame([ScimConstants::SERVICE_PROVIDER_CONFIG_SCHEMA], $payload['schemas']);
        self::assertSame(ScimConstants::RFC7644_URI, $payload['documentationUri']);
        self::assertTrue($payload['patch']['supported']);
        self::assertFalse($payload['bulk']['supported']);
        self::assertSame(200, $payload['filter']['maxResults']);
        self::assertCount(1, $payload['authenticationSchemes']);
        self::assertSame('oauthbearertoken', $payload['authenticationSchemes'][0]['type']);
        self::assertSame('https://example.com/scim/v2/ServiceProviderConfig', $payload['meta']['location']);
    }

    #[Test]
    public function serviceProviderConfigCustomValuesArePersistedInArray(): void
    {
        $config = new ServiceProviderConfig(
            changePasswordSupported: true,
            patchSupported: false,
            filterMaxResults: 500,
            sortSupported: true,
            etagSupported: true,
        );

        $payload = $config->toArray();

        self::assertTrue($payload['changePassword']['supported']);
        self::assertFalse($payload['patch']['supported']);
        self::assertSame(500, $payload['filter']['maxResults']);
        self::assertTrue($payload['sort']['supported']);
        self::assertTrue($payload['etag']['supported']);
    }

    #[Test]
    public function resourceTypeDefinitionEmitsSchemaAndMeta(): void
    {
        $def = new ResourceTypeDefinition(
            id: 'User',
            name: 'User',
            description: 'User Account',
            endpoint: '/Users',
            schema: ScimConstants::USER_SCHEMA,
            schemaExtensions: ['urn:ext:User'],
        );

        $payload = $def->toArray('https://example.com');

        self::assertSame([ScimConstants::RESOURCE_TYPE_SCHEMA], $payload['schemas']);
        self::assertSame('User', $payload['id']);
        self::assertSame('/Users', $payload['endpoint']);
        self::assertSame(ScimConstants::USER_SCHEMA, $payload['schema']);
        self::assertSame(['urn:ext:User'], $payload['schemaExtensions']);
        self::assertSame('https://example.com/scim/v2/ResourceTypes/User', $payload['meta']['location']);
    }

    #[Test]
    public function resourceTypeDefinitionDefaultsSchemaExtensionsToEmpty(): void
    {
        $def = new ResourceTypeDefinition(
            id: 'Group',
            name: 'Group',
            description: 'Group',
            endpoint: '/Groups',
            schema: ScimConstants::GROUP_SCHEMA,
        );

        self::assertSame([], $def->schemaExtensions);
    }

    #[Test]
    public function schemaDefinitionEmitsSchemaAndMeta(): void
    {
        $attrs = [
            ['name' => 'userName', 'type' => 'string', 'required' => true],
            ['name' => 'active', 'type' => 'boolean', 'required' => false],
        ];

        $def = new SchemaDefinition(
            id: ScimConstants::USER_SCHEMA,
            name: 'User',
            description: 'Core user resource',
            attributes: $attrs,
        );

        $payload = $def->toArray('https://example.com');

        self::assertSame([ScimConstants::SCHEMA_SCHEMA], $payload['schemas']);
        self::assertSame(ScimConstants::USER_SCHEMA, $payload['id']);
        self::assertSame('User', $payload['name']);
        self::assertSame($attrs, $payload['attributes']);
        self::assertStringEndsWith('/scim/v2/Schemas/' . ScimConstants::USER_SCHEMA, $payload['meta']['location']);
    }

    #[Test]
    public function scimConstantsExposesRfcSchemaUris(): void
    {
        self::assertStringStartsWith('urn:ietf:params:scim:', ScimConstants::USER_SCHEMA);
        self::assertStringStartsWith('urn:ietf:params:scim:', ScimConstants::GROUP_SCHEMA);
        self::assertStringStartsWith('urn:ietf:params:scim:', ScimConstants::ERROR_SCHEMA);
        self::assertStringStartsWith('urn:ietf:params:scim:', ScimConstants::PATCH_OP_SCHEMA);
        self::assertStringStartsWith('urn:ietf:params:scim:', ScimConstants::LIST_RESPONSE_SCHEMA);
        self::assertSame('application/scim+json', ScimConstants::CONTENT_TYPE);
    }

    #[Test]
    public function scimConstantsMetaKeysAllUseInternalPrefix(): void
    {
        foreach ([
            ScimConstants::META_EXTERNAL_ID,
            ScimConstants::META_ACTIVE,
            ScimConstants::META_TIMEZONE,
            ScimConstants::META_TITLE,
            ScimConstants::META_LAST_MODIFIED,
            ScimConstants::META_GROUP_PREFIX,
        ] as $meta) {
            self::assertStringStartsWith('_wppack_scim_', $meta);
        }
    }
}
