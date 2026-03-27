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

namespace WpPack\Component\Scim\Schema;

final class ScimConstants
{
    public const USER_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:User';
    public const GROUP_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:Group';
    public const ERROR_SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:Error';
    public const LIST_RESPONSE_SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';
    public const PATCH_OP_SCHEMA = 'urn:ietf:params:scim:api:messages:2.0:PatchOp';
    public const SERVICE_PROVIDER_CONFIG_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig';
    public const RESOURCE_TYPE_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:ResourceType';
    public const SCHEMA_SCHEMA = 'urn:ietf:params:scim:schemas:core:2.0:Schema';

    public const CONTENT_TYPE = 'application/scim+json';

    // Capability
    public const CAPABILITY_PROVISION = 'scim_provision';

    // User meta keys
    public const META_EXTERNAL_ID = '_wppack_scim_external_id';
    public const META_ACTIVE = '_wppack_scim_active';
    public const META_TIMEZONE = '_wppack_scim_timezone';
    public const META_TITLE = '_wppack_scim_title';
    public const META_LAST_MODIFIED = '_wppack_scim_last_modified';
}
