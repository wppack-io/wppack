<?php

declare(strict_types=1);

namespace WpPack\Component\Taxonomy\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Taxonomy\TermRepository;

#[CoversClass(TermRepository::class)]
final class TermRepositoryTest extends TestCase
{
    private TermRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new TermRepository();
    }

    #[Test]
    public function findAllReturnsArray(): void
    {
        $terms = $this->repository->findAll(['taxonomy' => 'category']);

        self::assertIsArray($terms);
    }

    #[Test]
    public function existsWithParentIdNullDoesNotFilterByParent(): void
    {
        $parentResult = wp_insert_term('Parent Exists ' . uniqid(), 'category');
        self::assertIsArray($parentResult);

        $parentId = (int) $parentResult['term_id'];
        $childName = 'Child Exists ' . uniqid();
        $childResult = wp_insert_term($childName, 'category', ['parent' => $parentId]);
        self::assertIsArray($childResult);

        $childId = (int) $childResult['term_id'];

        // Without parentId — should find the child regardless of parent
        $foundId = $this->repository->exists($childName, 'category');
        self::assertSame($childId, $foundId);

        // With explicit parentId — should find only under that parent
        $foundId = $this->repository->exists($childName, 'category', $parentId);
        self::assertSame($childId, $foundId);

        // With parentId = 0 — should NOT find the child (it's not top-level)
        $foundId = $this->repository->exists($childName, 'category', 0);
        self::assertNull($foundId);

        wp_delete_term($childId, 'category');
        wp_delete_term($parentId, 'category');
    }

    #[Test]
    public function findReturnsTermForValidId(): void
    {
        $result = wp_insert_term('Test Find ' . uniqid(), 'category');
        self::assertIsArray($result);

        $termId = (int) $result['term_id'];
        $term = $this->repository->find($termId, 'category');

        self::assertInstanceOf(\WP_Term::class, $term);
        self::assertSame($termId, $term->term_id);

        wp_delete_term($termId, 'category');
    }

    #[Test]
    public function findReturnsNullForInvalidId(): void
    {
        self::assertNull($this->repository->find(999999, 'category'));
    }

    #[Test]
    public function findBySlugReturnsTerm(): void
    {
        $slug = 'test-slug-' . uniqid();
        $result = wp_insert_term('Test Slug', 'category', ['slug' => $slug]);
        self::assertIsArray($result);

        $term = $this->repository->findBySlug($slug, 'category');

        self::assertInstanceOf(\WP_Term::class, $term);
        self::assertSame($slug, $term->slug);

        wp_delete_term((int) $result['term_id'], 'category');
    }

    #[Test]
    public function findBySlugReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->repository->findBySlug('nonexistent-' . uniqid(), 'category'));
    }

    #[Test]
    public function findByNameReturnsTerm(): void
    {
        $name = 'Test Name ' . uniqid();
        $result = wp_insert_term($name, 'category');
        self::assertIsArray($result);

        $term = $this->repository->findByName($name, 'category');

        self::assertInstanceOf(\WP_Term::class, $term);
        self::assertSame($name, $term->name);

        wp_delete_term((int) $result['term_id'], 'category');
    }

    #[Test]
    public function findByNameReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->repository->findByName('Nonexistent Name ' . uniqid(), 'category'));
    }

    #[Test]
    public function existsReturnsTermIdWhenFound(): void
    {
        $name = 'Test Exists ' . uniqid();
        $result = wp_insert_term($name, 'category');
        self::assertIsArray($result);

        $termId = $this->repository->exists($name, 'category');

        self::assertSame((int) $result['term_id'], $termId);

        wp_delete_term((int) $result['term_id'], 'category');
    }

    #[Test]
    public function existsReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->repository->exists('Nonexistent Term ' . uniqid(), 'category'));
    }

    #[Test]
    public function insertCreatesTerm(): void
    {
        $name = 'Test Insert ' . uniqid();
        $result = $this->repository->insert($name, 'category');

        self::assertIsArray($result);
        self::assertArrayHasKey('term_id', $result);
        self::assertArrayHasKey('term_taxonomy_id', $result);

        $term = get_term((int) $result['term_id'], 'category');
        self::assertInstanceOf(\WP_Term::class, $term);
        self::assertSame($name, $term->name);

        wp_delete_term((int) $result['term_id'], 'category');
    }

    #[Test]
    public function updateModifiesTerm(): void
    {
        $result = wp_insert_term('Test Update Before ' . uniqid(), 'category');
        self::assertIsArray($result);

        $termId = (int) $result['term_id'];
        $newName = 'Test Update After ' . uniqid();
        $updateResult = $this->repository->update($termId, 'category', ['name' => $newName]);

        self::assertIsArray($updateResult);

        $term = get_term($termId, 'category');
        self::assertInstanceOf(\WP_Term::class, $term);
        self::assertSame($newName, $term->name);

        wp_delete_term($termId, 'category');
    }

    #[Test]
    public function deleteRemovesTerm(): void
    {
        $result = wp_insert_term('Test Delete ' . uniqid(), 'category');
        self::assertIsArray($result);

        $termId = (int) $result['term_id'];
        $deleted = $this->repository->delete($termId, 'category');

        self::assertTrue($deleted);

        $term = get_term($termId, 'category');
        self::assertNull($term);
    }

    #[Test]
    public function metaCrud(): void
    {
        $result = wp_insert_term('Test Meta ' . uniqid(), 'category');
        self::assertIsArray($result);

        $termId = (int) $result['term_id'];

        // addMeta
        $metaId = $this->repository->addMeta($termId, 'test_key', 'test_value');
        self::assertIsInt($metaId);
        self::assertGreaterThan(0, $metaId);

        // getMeta (single)
        $value = $this->repository->getMeta($termId, 'test_key', true);
        self::assertSame('test_value', $value);

        // getMeta (all for key)
        $values = $this->repository->getMeta($termId, 'test_key');
        self::assertIsArray($values);
        self::assertContains('test_value', $values);

        // updateMeta
        $this->repository->updateMeta($termId, 'test_key', 'updated_value');
        $value = $this->repository->getMeta($termId, 'test_key', true);
        self::assertSame('updated_value', $value);

        // deleteMeta
        $deleted = $this->repository->deleteMeta($termId, 'test_key');
        self::assertTrue($deleted);

        $value = $this->repository->getMeta($termId, 'test_key', true);
        self::assertSame('', $value);

        wp_delete_term($termId, 'category');
    }

    #[Test]
    public function setObjectTermsSetsTerms(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-set-terms', 'post_status' => 'publish']);
        self::assertIsInt($postId);

        $term1 = wp_insert_term('Set Term A ' . uniqid(), 'category');
        $term2 = wp_insert_term('Set Term B ' . uniqid(), 'category');
        self::assertIsArray($term1);
        self::assertIsArray($term2);

        $result = $this->repository->setObjectTerms($postId, [(int) $term1['term_id'], (int) $term2['term_id']], 'category');

        self::assertIsArray($result);
        self::assertCount(2, $result);

        $objectTerms = wp_get_object_terms($postId, 'category', ['fields' => 'ids']);
        self::assertContains((int) $term1['term_id'], $objectTerms);
        self::assertContains((int) $term2['term_id'], $objectTerms);

        wp_delete_post($postId, true);
        wp_delete_term((int) $term1['term_id'], 'category');
        wp_delete_term((int) $term2['term_id'], 'category');
    }

    #[Test]
    public function addObjectTermsAppendsTerms(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-add-terms', 'post_status' => 'publish']);
        self::assertIsInt($postId);

        $term1 = wp_insert_term('Add Term A ' . uniqid(), 'category');
        $term2 = wp_insert_term('Add Term B ' . uniqid(), 'category');
        self::assertIsArray($term1);
        self::assertIsArray($term2);

        wp_set_object_terms($postId, [(int) $term1['term_id']], 'category');
        $this->repository->addObjectTerms($postId, [(int) $term2['term_id']], 'category');

        $objectTerms = wp_get_object_terms($postId, 'category', ['fields' => 'ids']);
        self::assertContains((int) $term1['term_id'], $objectTerms);
        self::assertContains((int) $term2['term_id'], $objectTerms);

        wp_delete_post($postId, true);
        wp_delete_term((int) $term1['term_id'], 'category');
        wp_delete_term((int) $term2['term_id'], 'category');
    }

    #[Test]
    public function removeObjectTermsRemovesTerm(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-remove-terms', 'post_status' => 'publish']);
        self::assertIsInt($postId);

        $term = wp_insert_term('Remove Term ' . uniqid(), 'category');
        self::assertIsArray($term);

        $termId = (int) $term['term_id'];
        wp_set_object_terms($postId, [$termId], 'category');

        $result = $this->repository->removeObjectTerms($postId, [$termId], 'category');

        self::assertTrue($result);

        $objectTerms = wp_get_object_terms($postId, 'category', ['fields' => 'ids']);
        self::assertNotContains($termId, $objectTerms);

        wp_delete_post($postId, true);
        wp_delete_term($termId, 'category');
    }

    #[Test]
    public function getObjectTermsReturnsTerms(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-get-terms', 'post_status' => 'publish']);
        self::assertIsInt($postId);

        $term = wp_insert_term('Get Term ' . uniqid(), 'category');
        self::assertIsArray($term);

        $termId = (int) $term['term_id'];
        wp_set_object_terms($postId, [$termId], 'category');

        $result = $this->repository->getObjectTerms($postId, 'category');

        self::assertIsArray($result);
        self::assertNotEmpty($result);
        self::assertInstanceOf(\WP_Term::class, $result[0]);

        wp_delete_post($postId, true);
        wp_delete_term($termId, 'category');
    }
}
