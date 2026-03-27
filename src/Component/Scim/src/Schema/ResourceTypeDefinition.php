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

final readonly class ResourceTypeDefinition
{
    /**
     * @param list<string> $schemaExtensions
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $endpoint,
        public string $schema,
        public array $schemaExtensions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schemas' => [ScimConstants::RESOURCE_TYPE_SCHEMA],
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'endpoint' => $this->endpoint,
            'schema' => $this->schema,
            'schemaExtensions' => $this->schemaExtensions,
            'meta' => [
                'resourceType' => 'ResourceType',
                'location' => '/scim/v2/ResourceTypes/' . $this->id,
            ],
        ];
    }
}
