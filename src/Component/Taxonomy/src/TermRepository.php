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

namespace WPPack\Component\Taxonomy;

use WPPack\Component\Taxonomy\Exception\TermException;

final readonly class TermRepository implements TermRepositoryInterface
{
    public function findAll(array $args = []): array
    {
        $result = get_terms($args);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        // The WP stub for get_terms() models the value union loosely as
        // array<int|string|WP_Term>|string. This repository only exposes
        // the WP_Term listing path; narrow + array_values for list<WP_Term>.
        if (!\is_array($result)) {
            return [];
        }

        return array_values(array_filter(
            $result,
            static fn($term): bool => $term instanceof \WP_Term,
        ));
    }

    public function find(int $termId, string $taxonomy = ''): ?\WP_Term
    {
        $term = get_term($termId, $taxonomy);

        return $term instanceof \WP_Term ? $term : null;
    }

    public function findBySlug(string $slug, string $taxonomy): ?\WP_Term
    {
        return $this->findBy('slug', $slug, $taxonomy);
    }

    public function findByName(string $name, string $taxonomy): ?\WP_Term
    {
        return $this->findBy('name', $name, $taxonomy);
    }

    public function exists(int|string $term, string $taxonomy = '', ?int $parentId = null): ?int
    {
        $result = term_exists($term, $taxonomy !== '' ? $taxonomy : null, $parentId);

        if (\is_array($result)) {
            return (int) $result['term_id'];
        }

        if (\is_int($result) && $result > 0) {
            return $result;
        }

        return null;
    }

    /**
     * @return array{term_id: int, term_taxonomy_id: int}
     */
    public function insert(string $term, string $taxonomy, array $args = []): array
    {
        $result = wp_insert_term($term, $taxonomy, $args);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        /** @var array{term_id: int, term_taxonomy_id: int} $result */
        return $result;
    }

    /**
     * @return array{term_id: int, term_taxonomy_id: int}
     */
    public function update(int $termId, string $taxonomy, array $args = []): array
    {
        $result = wp_update_term($termId, $taxonomy, $args);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        /** @var array{term_id: int, term_taxonomy_id: int} $result */
        return $result;
    }

    public function delete(int $termId, string $taxonomy, array $args = []): bool
    {
        $result = wp_delete_term($termId, $taxonomy, $args);

        return $result === true;
    }

    public function getMeta(int $termId, string $key = '', bool $single = false): mixed
    {
        return get_term_meta($termId, $key, $single);
    }

    public function addMeta(int $termId, string $key, mixed $value, bool $unique = false): ?int
    {
        $result = add_term_meta($termId, $key, $value, $unique);

        return $result === false ? null : $result;
    }

    public function updateMeta(int $termId, string $key, mixed $value, mixed $previousValue = ''): int|bool
    {
        $result = update_term_meta($termId, $key, $value, $previousValue);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        return $result;
    }

    public function deleteMeta(int $termId, string $key, mixed $value = ''): bool
    {
        return delete_term_meta($termId, $key, $value);
    }

    public function setObjectTerms(int $objectId, array $terms, string $taxonomy, bool $append = false): array
    {
        $result = wp_set_object_terms($objectId, $terms, $taxonomy, $append);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        return array_values(array_map('intval', $result));
    }

    public function addObjectTerms(int $objectId, array $terms, string $taxonomy): array
    {
        $result = wp_add_object_terms($objectId, $terms, $taxonomy);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        return array_values(array_map('intval', $result));
    }

    public function removeObjectTerms(int $objectId, array $terms, string $taxonomy): bool
    {
        $result = wp_remove_object_terms($objectId, $terms, $taxonomy);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        return $result;
    }

    public function getObjectTerms(int|array $objectIds, string|array $taxonomies, array $args = []): array
    {
        $result = wp_get_object_terms($objectIds, $taxonomies, $args);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        // wp_get_object_terms() can return string under 'fields' => 'all'
        // (terms as a comma-separated string) per the WP stub union; we
        // don't expose that mode, so guard + filter to list<WP_Term>.
        if (!\is_array($result)) {
            return [];
        }

        return array_values(array_filter(
            $result,
            static fn($term): bool => $term instanceof \WP_Term,
        ));
    }

    private function findBy(string $field, string $value, string $taxonomy): ?\WP_Term
    {
        $term = get_term_by($field, $value, $taxonomy);

        return $term instanceof \WP_Term ? $term : null;
    }
}
