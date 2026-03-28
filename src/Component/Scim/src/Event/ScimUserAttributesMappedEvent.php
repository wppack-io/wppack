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

namespace WpPack\Component\Scim\Event;

use WpPack\Component\EventDispatcher\Event;

final class ScimUserAttributesMappedEvent extends Event
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $scimAttributes
     */
    public function __construct(
        private array $data,
        private array $meta,
        private readonly array $scimAttributes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }

    /**
     * @return array<string, mixed>
     */
    public function getScimAttributes(): array
    {
        return $this->scimAttributes;
    }
}
