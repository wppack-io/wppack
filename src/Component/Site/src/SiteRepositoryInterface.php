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

namespace WPPack\Component\Site;

interface SiteRepositoryInterface
{
    /**
     * @param array<string, mixed> $args get_sites() arguments
     *
     * @return list<\WP_Site>
     */
    public function findAll(array $args = []): array;

    public function find(int $blogId): ?\WP_Site;

    public function findByUrl(string $domain, string $path = '/'): ?\WP_Site;

    public function findBySlug(string $slug): ?\WP_Site;

    /**
     * @return list<string>
     */
    public function getAllDomains(): array;

    public function getMeta(int $blogId, string $key = '', bool $single = false): mixed;

    public function addMeta(int $blogId, string $key, mixed $value, bool $unique = false): ?int;

    public function updateMeta(int $blogId, string $key, mixed $value, mixed $previousValue = ''): int|bool;

    public function deleteMeta(int $blogId, string $key, mixed $value = ''): bool;
}
