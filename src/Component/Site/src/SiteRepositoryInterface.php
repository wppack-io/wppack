<?php

declare(strict_types=1);

namespace WpPack\Component\Site;

interface SiteRepositoryInterface
{
    /**
     * @param array<string, mixed> $args get_sites() arguments
     *
     * @return list<\WP_Site>
     */
    public function findAll(array $args = []): array;

    public function findById(int $blogId): ?\WP_Site;

    public function findByUrl(string $domain, string $path = '/'): ?int;

    public function findBySlug(string $slug): ?int;

    /**
     * @return list<string>
     */
    public function getAllDomains(): array;
}
