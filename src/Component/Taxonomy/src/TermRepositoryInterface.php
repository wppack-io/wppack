<?php

declare(strict_types=1);

namespace WpPack\Component\Taxonomy;

interface TermRepositoryInterface
{
    /**
     * @param array<string, mixed> $args get_terms() arguments
     *
     * @return list<\WP_Term>|\WP_Error
     */
    public function findAll(array $args = []): array|\WP_Error;

    public function find(int $termId, string $taxonomy = ''): ?\WP_Term;

    public function findBySlug(string $slug, string $taxonomy): ?\WP_Term;

    public function findByName(string $name, string $taxonomy): ?\WP_Term;

    public function exists(int|string $term, string $taxonomy = '', ?int $parentId = null): ?int;

    /**
     * @param array<string, mixed> $args
     *
     * @return array{term_id: int, term_taxonomy_id: int}|\WP_Error
     */
    public function insert(string $term, string $taxonomy, array $args = []): array|\WP_Error;

    /**
     * @param array<string, mixed> $args
     *
     * @return array{term_id: int, term_taxonomy_id: int}|\WP_Error
     */
    public function update(int $termId, string $taxonomy, array $args = []): array|\WP_Error;

    /**
     * @param array<string, mixed> $args
     */
    public function delete(int $termId, string $taxonomy, array $args = []): bool;

    public function getMeta(int $termId, string $key = '', bool $single = false): mixed;

    public function addMeta(int $termId, string $key, mixed $value, bool $unique = false): int|false;

    public function updateMeta(int $termId, string $key, mixed $value, mixed $previousValue = ''): int|bool;

    public function deleteMeta(int $termId, string $key, mixed $value = ''): bool;

    /**
     * @param list<int|string> $terms
     *
     * @return list<int>|\WP_Error
     */
    public function setObjectTerms(int $objectId, array $terms, string $taxonomy, bool $append = false): array|\WP_Error;

    /**
     * @param list<int|string> $terms
     *
     * @return list<int>|\WP_Error
     */
    public function addObjectTerms(int $objectId, array $terms, string $taxonomy): array|\WP_Error;

    /**
     * @param list<int|string> $terms
     */
    public function removeObjectTerms(int $objectId, array $terms, string $taxonomy): bool|\WP_Error;

    /**
     * @param int|list<int> $objectIds
     * @param string|list<string> $taxonomies
     * @param array<string, mixed> $args
     *
     * @return list<\WP_Term>|\WP_Error
     */
    public function getObjectTerms(int|array $objectIds, string|array $taxonomies, array $args = []): array|\WP_Error;
}
