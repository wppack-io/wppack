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

final class UserSchema
{
    public static function definition(): SchemaDefinition
    {
        return new SchemaDefinition(
            id: ScimConstants::USER_SCHEMA,
            name: 'User',
            description: 'User Account',
            attributes: self::attributes(),
        );
    }

    public static function resourceType(): ResourceTypeDefinition
    {
        return new ResourceTypeDefinition(
            id: 'User',
            name: 'User',
            description: 'User Account',
            endpoint: '/Users',
            schema: ScimConstants::USER_SCHEMA,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function attributes(): array
    {
        return [
            [
                'name' => 'userName',
                'type' => 'string',
                'multiValued' => false,
                'required' => true,
                'caseExact' => false,
                'mutability' => 'immutable',
                'returned' => 'default',
                'uniqueness' => 'server',
            ],
            [
                'name' => 'name',
                'type' => 'complex',
                'multiValued' => false,
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
                'subAttributes' => [
                    [
                        'name' => 'givenName',
                        'type' => 'string',
                        'multiValued' => false,
                        'required' => false,
                        'mutability' => 'readWrite',
                        'returned' => 'default',
                    ],
                    [
                        'name' => 'familyName',
                        'type' => 'string',
                        'multiValued' => false,
                        'required' => false,
                        'mutability' => 'readWrite',
                        'returned' => 'default',
                    ],
                ],
            ],
            [
                'name' => 'displayName',
                'type' => 'string',
                'multiValued' => false,
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'nickName',
                'type' => 'string',
                'multiValued' => false,
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'profileUrl',
                'type' => 'reference',
                'multiValued' => false,
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
                'referenceTypes' => ['external'],
            ],
            [
                'name' => 'emails',
                'type' => 'complex',
                'multiValued' => true,
                'required' => true,
                'mutability' => 'readWrite',
                'returned' => 'default',
                'subAttributes' => [
                    [
                        'name' => 'value',
                        'type' => 'string',
                        'multiValued' => false,
                        'required' => true,
                        'mutability' => 'readWrite',
                        'returned' => 'default',
                    ],
                    [
                        'name' => 'primary',
                        'type' => 'boolean',
                        'multiValued' => false,
                        'required' => false,
                        'mutability' => 'readWrite',
                        'returned' => 'default',
                    ],
                    [
                        'name' => 'type',
                        'type' => 'string',
                        'multiValued' => false,
                        'required' => false,
                        'mutability' => 'readWrite',
                        'returned' => 'default',
                        'canonicalValues' => ['work', 'home', 'other'],
                    ],
                ],
            ],
            [
                'name' => 'active',
                'type' => 'boolean',
                'multiValued' => false,
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'locale',
                'type' => 'string',
                'multiValued' => false,
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'timezone',
                'type' => 'string',
                'multiValued' => false,
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'title',
                'type' => 'string',
                'multiValued' => false,
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'externalId',
                'type' => 'string',
                'multiValued' => false,
                'required' => false,
                'caseExact' => true,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
        ];
    }
}
