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

final readonly class SchemaDefinition
{
    /**
     * @param list<array<string, mixed>> $attributes
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public array $attributes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $baseUrl = ''): array
    {
        return [
            'schemas' => [ScimConstants::SCHEMA_SCHEMA],
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'attributes' => $this->attributes,
            'meta' => [
                'resourceType' => 'Schema',
                'location' => $baseUrl . '/scim/v2/Schemas/' . $this->id,
            ],
        ];
    }
}
