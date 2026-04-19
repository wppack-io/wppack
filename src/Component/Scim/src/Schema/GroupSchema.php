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

namespace WPPack\Component\Scim\Schema;

final class GroupSchema
{
    private function __construct() {}

    public static function definition(): SchemaDefinition
    {
        return new SchemaDefinition(
            id: ScimConstants::GROUP_SCHEMA,
            name: 'Group',
            description: 'Group',
            attributes: self::attributes(),
        );
    }

    public static function resourceType(): ResourceTypeDefinition
    {
        return new ResourceTypeDefinition(
            id: 'Group',
            name: 'Group',
            description: 'Group',
            endpoint: '/Groups',
            schema: ScimConstants::GROUP_SCHEMA,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function attributes(): array
    {
        return [
            [
                'name' => 'displayName',
                'type' => 'string',
                'multiValued' => false,
                'required' => true,
                'mutability' => 'readWrite',
                'returned' => 'default',
            ],
            [
                'name' => 'members',
                'type' => 'complex',
                'multiValued' => true,
                'required' => false,
                'mutability' => 'readWrite',
                'returned' => 'default',
                'subAttributes' => [
                    [
                        'name' => 'value',
                        'type' => 'string',
                        'multiValued' => false,
                        'required' => true,
                        'mutability' => 'immutable',
                        'returned' => 'default',
                    ],
                    [
                        'name' => 'display',
                        'type' => 'string',
                        'multiValued' => false,
                        'required' => false,
                        'mutability' => 'readOnly',
                        'returned' => 'default',
                    ],
                    [
                        'name' => '$ref',
                        'type' => 'reference',
                        'multiValued' => false,
                        'required' => false,
                        'mutability' => 'readOnly',
                        'returned' => 'default',
                        'referenceTypes' => ['User'],
                    ],
                ],
            ],
        ];
    }
}
