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

final readonly class SiteRepository implements SiteRepositoryInterface
{
    public function findAll(array $args = []): array
    {
        if (!\function_exists('get_sites')) {
            return [];
        }

        // The WP stub for get_sites() models the value union loosely as
        // array<int|WP_Site>|int (int element appears when 'count' => true
        // is passed, in which case the result is also int total). This
        // repository only exposes the listing path, so guard + filter to
        // narrow to a clean list<WP_Site>.
        $sites = get_sites($args);
        if (!\is_array($sites)) {
            return [];
        }

        return array_values(array_filter(
            $sites,
            static fn($site): bool => $site instanceof \WP_Site,
        ));
    }

    public function find(int $blogId): ?\WP_Site
    {
        if (!\function_exists('get_blog_details')) {
            return null;
        }

        $site = get_blog_details($blogId);

        return $site instanceof \WP_Site ? $site : null;
    }

    public function findByUrl(string $domain, string $path = '/'): ?\WP_Site
    {
        if (!\function_exists('get_blog_id_from_url')) {
            return null;
        }

        $id = get_blog_id_from_url($domain, $path);

        return $id > 0 ? $this->find($id) : null;
    }

    public function findBySlug(string $slug): ?\WP_Site
    {
        if (!\function_exists('get_id_from_blogname')) {
            return null;
        }

        $id = get_id_from_blogname($slug);

        return $id > 0 ? $this->find($id) : null;
    }

    public function getAllDomains(): array
    {
        if (!\function_exists('get_sites')) {
            return [];
        }

        // 'count' is not set, so get_sites returns array<int, WP_Site>;
        // is_array() narrows away the int return that the stub also models.
        $sites = get_sites(['number' => 0]);
        if (!\is_array($sites)) {
            return [];
        }

        return array_values(array_unique(
            array_map(static fn(\WP_Site $site): string => $site->domain, $sites),
        ));
    }

    public function getMeta(int $blogId, string $key = '', bool $single = false): mixed
    {
        if (!\function_exists('get_site_meta')) {
            return $single ? '' : [];
        }

        return get_site_meta($blogId, $key, $single);
    }

    public function addMeta(int $blogId, string $key, mixed $value, bool $unique = false): ?int
    {
        if (!\function_exists('add_site_meta')) {
            return null;
        }

        $result = add_site_meta($blogId, $key, $value, $unique);

        return $result === false ? null : $result;
    }

    public function updateMeta(int $blogId, string $key, mixed $value, mixed $previousValue = ''): int|bool
    {
        if (!\function_exists('update_site_meta')) {
            return false;
        }

        return update_site_meta($blogId, $key, $value, $previousValue);
    }

    public function deleteMeta(int $blogId, string $key, mixed $value = ''): bool
    {
        if (!\function_exists('delete_site_meta')) {
            return false;
        }

        return delete_site_meta($blogId, $key, $value);
    }
}
