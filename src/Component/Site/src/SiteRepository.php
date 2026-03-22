<?php

declare(strict_types=1);

namespace WpPack\Component\Site;

final readonly class SiteRepository implements SiteRepositoryInterface
{
    public function findAll(array $args = []): array
    {
        if (!\function_exists('get_sites')) {
            return [];
        }

        return get_sites($args);
    }

    public function findById(int $blogId): ?\WP_Site
    {
        if (!\function_exists('get_blog_details')) {
            return null;
        }

        $site = get_blog_details($blogId);

        return $site instanceof \WP_Site ? $site : null;
    }

    public function findByUrl(string $domain, string $path = '/'): ?int
    {
        if (!\function_exists('get_blog_id_from_url')) {
            return null;
        }

        $id = get_blog_id_from_url($domain, $path);

        return $id > 0 ? $id : null;
    }

    public function findBySlug(string $slug): ?int
    {
        if (!\function_exists('get_id_from_blogname')) {
            return null;
        }

        return get_id_from_blogname($slug);
    }

    public function getAllDomains(): array
    {
        if (!\function_exists('get_sites')) {
            return [];
        }

        $sites = get_sites(['number' => 0]);

        return array_values(array_unique(
            array_map(static fn(\WP_Site $site): string => $site->domain, $sites),
        ));
    }
}
