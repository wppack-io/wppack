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

        return $result;
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

    public function insert(string $term, string $taxonomy, array $args = []): array
    {
        $result = wp_insert_term($term, $taxonomy, $args);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        return $result;
    }

    public function update(int $termId, string $taxonomy, array $args = []): array
    {
        $result = wp_update_term($termId, $taxonomy, $args);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

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
        return update_term_meta($termId, $key, $value, $previousValue);
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

        return $result;
    }

    public function addObjectTerms(int $objectId, array $terms, string $taxonomy): array
    {
        $result = wp_add_object_terms($objectId, $terms, $taxonomy);

        if ($result instanceof \WP_Error) {
            throw TermException::fromWpError($result);
        }

        return $result;
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

        return $result;
    }

    private function findBy(string $field, string $value, string $taxonomy): ?\WP_Term
    {
        $term = get_term_by($field, $value, $taxonomy);

        return $term instanceof \WP_Term ? $term : null;
    }
}
