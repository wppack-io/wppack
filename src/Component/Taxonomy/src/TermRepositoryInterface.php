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

namespace WpPack\Component\Taxonomy;

use WpPack\Component\Taxonomy\Exception\TermException;

interface TermRepositoryInterface
{
    /**
     * @param array<string, mixed> $args get_terms() arguments
     *
     * @return list<\WP_Term>
     *
     * @throws TermException
     */
    public function findAll(array $args = []): array;

    public function find(int $termId, string $taxonomy = ''): ?\WP_Term;

    public function findBySlug(string $slug, string $taxonomy): ?\WP_Term;

    public function findByName(string $name, string $taxonomy): ?\WP_Term;

    public function exists(int|string $term, string $taxonomy = '', ?int $parentId = null): ?int;

    /**
     * @param array<string, mixed> $args
     *
     * @return array{term_id: int, term_taxonomy_id: int}
     *
     * @throws TermException
     */
    public function insert(string $term, string $taxonomy, array $args = []): array;

    /**
     * @param array<string, mixed> $args
     *
     * @return array{term_id: int, term_taxonomy_id: int}
     *
     * @throws TermException
     */
    public function update(int $termId, string $taxonomy, array $args = []): array;

    /**
     * @param array<string, mixed> $args
     */
    public function delete(int $termId, string $taxonomy, array $args = []): bool;

    public function getMeta(int $termId, string $key = '', bool $single = false): mixed;

    public function addMeta(int $termId, string $key, mixed $value, bool $unique = false): ?int;

    public function updateMeta(int $termId, string $key, mixed $value, mixed $previousValue = ''): int|bool;

    public function deleteMeta(int $termId, string $key, mixed $value = ''): bool;

    /**
     * @param list<int|string> $terms
     *
     * @return list<int>
     *
     * @throws TermException
     */
    public function setObjectTerms(int $objectId, array $terms, string $taxonomy, bool $append = false): array;

    /**
     * @param list<int|string> $terms
     *
     * @return list<int>
     *
     * @throws TermException
     */
    public function addObjectTerms(int $objectId, array $terms, string $taxonomy): array;

    /**
     * @param list<int|string> $terms
     *
     * @throws TermException
     */
    public function removeObjectTerms(int $objectId, array $terms, string $taxonomy): bool;

    /**
     * @param int|list<int> $objectIds
     * @param string|list<string> $taxonomies
     * @param array<string, mixed> $args
     *
     * @return list<\WP_Term>
     *
     * @throws TermException
     */
    public function getObjectTerms(int|array $objectIds, string|array $taxonomies, array $args = []): array;
}
