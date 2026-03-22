<?php

declare(strict_types=1);

namespace WpPack\Component\Taxonomy;

final readonly class TermRepository implements TermRepositoryInterface
{
    public function find(int $termId, string $taxonomy = ''): ?\WP_Term
    {
        if (!\function_exists('get_term')) {
            return null;
        }

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
        if (!\function_exists('term_exists')) {
            return null;
        }

        $args = $parentId !== null ? ['parent' => $parentId] : [];
        $result = term_exists($term, $taxonomy !== '' ? $taxonomy : null, $parentId ?? 0);

        if (\is_array($result)) {
            return (int) $result['term_id'];
        }

        if (\is_int($result) && $result > 0) {
            return $result;
        }

        return null;
    }

    public function insert(string $term, string $taxonomy, array $args = []): array|\WP_Error
    {
        if (!\function_exists('wp_insert_term')) {
            return new \WP_Error('missing_function', 'wp_insert_term() is not available.');
        }

        return wp_insert_term($term, $taxonomy, $args);
    }

    public function update(int $termId, string $taxonomy, array $args = []): array|\WP_Error
    {
        if (!\function_exists('wp_update_term')) {
            return new \WP_Error('missing_function', 'wp_update_term() is not available.');
        }

        return wp_update_term($termId, $taxonomy, $args);
    }

    public function delete(int $termId, string $taxonomy, array $args = []): bool
    {
        if (!\function_exists('wp_delete_term')) {
            return false;
        }

        $result = wp_delete_term($termId, $taxonomy, $args);

        return $result === true;
    }

    public function getMeta(int $termId, string $key = '', bool $single = false): mixed
    {
        if (!\function_exists('get_term_meta')) {
            return $single ? '' : [];
        }

        return get_term_meta($termId, $key, $single);
    }

    public function addMeta(int $termId, string $key, mixed $value, bool $unique = false): int|false
    {
        if (!\function_exists('add_term_meta')) {
            return false;
        }

        return add_term_meta($termId, $key, $value, $unique);
    }

    public function updateMeta(int $termId, string $key, mixed $value, mixed $previousValue = ''): int|bool
    {
        if (!\function_exists('update_term_meta')) {
            return false;
        }

        return update_term_meta($termId, $key, $value, $previousValue);
    }

    public function deleteMeta(int $termId, string $key, mixed $value = ''): bool
    {
        if (!\function_exists('delete_term_meta')) {
            return false;
        }

        return delete_term_meta($termId, $key, $value);
    }

    public function setObjectTerms(int $objectId, array $terms, string $taxonomy, bool $append = false): array|\WP_Error
    {
        if (!\function_exists('wp_set_object_terms')) {
            return new \WP_Error('missing_function', 'wp_set_object_terms() is not available.');
        }

        return wp_set_object_terms($objectId, $terms, $taxonomy, $append);
    }

    public function addObjectTerms(int $objectId, array $terms, string $taxonomy): array|\WP_Error
    {
        if (!\function_exists('wp_add_object_terms')) {
            return new \WP_Error('missing_function', 'wp_add_object_terms() is not available.');
        }

        return wp_add_object_terms($objectId, $terms, $taxonomy);
    }

    public function removeObjectTerms(int $objectId, array $terms, string $taxonomy): bool|\WP_Error
    {
        if (!\function_exists('wp_remove_object_terms')) {
            return new \WP_Error('missing_function', 'wp_remove_object_terms() is not available.');
        }

        return wp_remove_object_terms($objectId, $terms, $taxonomy);
    }

    public function getObjectTerms(int|array $objectIds, string|array $taxonomies, array $args = []): array|\WP_Error
    {
        if (!\function_exists('wp_get_object_terms')) {
            return new \WP_Error('missing_function', 'wp_get_object_terms() is not available.');
        }

        return wp_get_object_terms($objectIds, $taxonomies, $args);
    }

    private function findBy(string $field, string $value, string $taxonomy): ?\WP_Term
    {
        if (!\function_exists('get_term_by')) {
            return null;
        }

        $term = get_term_by($field, $value, $taxonomy);

        return $term instanceof \WP_Term ? $term : null;
    }
}
