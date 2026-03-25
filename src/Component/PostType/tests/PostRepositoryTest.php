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

namespace WpPack\Component\PostType\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\PostType\Exception\PostException;
use WpPack\Component\PostType\PostRepository;

#[CoversClass(PostRepository::class)]
final class PostRepositoryTest extends TestCase
{
    private PostRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new PostRepository();
    }

    #[Test]
    public function findReturnsPostForValidId(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-find', 'post_status' => 'publish']);
        self::assertIsInt($postId);

        $post = $this->repository->find($postId);

        self::assertInstanceOf(\WP_Post::class, $post);
        self::assertSame($postId, $post->ID);

        wp_delete_post($postId, true);
    }

    #[Test]
    public function findReturnsNullForInvalidId(): void
    {
        self::assertNull($this->repository->find(999999));
    }

    #[Test]
    public function findAllReturnsArray(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-find-all', 'post_status' => 'publish']);
        self::assertIsInt($postId);

        $posts = $this->repository->findAll(['post_status' => 'publish']);

        self::assertIsArray($posts);
        self::assertNotEmpty($posts);

        wp_delete_post($postId, true);
    }

    #[Test]
    public function insertCreatesPost(): void
    {
        $result = $this->repository->insert(['post_title' => 'test-insert', 'post_status' => 'draft']);

        self::assertIsInt($result);
        self::assertGreaterThan(0, $result);

        $post = get_post($result);
        self::assertInstanceOf(\WP_Post::class, $post);
        self::assertSame('test-insert', $post->post_title);

        wp_delete_post($result, true);
    }

    #[Test]
    public function insertThrowsOnInvalidData(): void
    {
        $this->expectException(PostException::class);

        $this->repository->insert(['post_type' => 'nonexistent_post_type_' . uniqid()]);
    }

    #[Test]
    public function updateModifiesPost(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-update-before', 'post_status' => 'draft']);
        self::assertIsInt($postId);

        $result = $this->repository->update(['ID' => $postId, 'post_title' => 'test-update-after']);

        self::assertIsInt($result);
        self::assertSame($postId, $result);

        $post = get_post($postId);
        self::assertInstanceOf(\WP_Post::class, $post);
        self::assertSame('test-update-after', $post->post_title);

        wp_delete_post($postId, true);
    }

    #[Test]
    public function deleteRemovesPost(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-delete', 'post_status' => 'draft']);
        self::assertIsInt($postId);

        $result = $this->repository->delete($postId, true);

        self::assertInstanceOf(\WP_Post::class, $result);
        self::assertNull(get_post($postId));
    }

    #[Test]
    public function deleteReturnsNullForInvalidId(): void
    {
        self::assertNull($this->repository->delete(999999, true));
    }

    #[Test]
    public function trashMovesPostToTrash(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-trash', 'post_status' => 'publish']);
        self::assertIsInt($postId);

        $result = $this->repository->trash($postId);

        self::assertInstanceOf(\WP_Post::class, $result);

        $post = get_post($postId);
        self::assertInstanceOf(\WP_Post::class, $post);
        self::assertSame('trash', $post->post_status);

        wp_delete_post($postId, true);
    }

    #[Test]
    public function untrashRestoresPost(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-untrash', 'post_status' => 'publish']);
        self::assertIsInt($postId);

        wp_trash_post($postId);
        $result = $this->repository->untrash($postId);

        self::assertInstanceOf(\WP_Post::class, $result);

        $post = get_post($postId);
        self::assertInstanceOf(\WP_Post::class, $post);
        self::assertNotSame('trash', $post->post_status);

        wp_delete_post($postId, true);
    }

    #[Test]
    public function metaCrud(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-meta', 'post_status' => 'draft']);
        self::assertIsInt($postId);

        // addMeta
        $metaId = $this->repository->addMeta($postId, 'test_key', 'test_value');
        self::assertIsInt($metaId);
        self::assertGreaterThan(0, $metaId);

        // getMeta (single)
        $value = $this->repository->getMeta($postId, 'test_key', true);
        self::assertSame('test_value', $value);

        // getMeta (all for key)
        $values = $this->repository->getMeta($postId, 'test_key');
        self::assertIsArray($values);
        self::assertContains('test_value', $values);

        // updateMeta
        $this->repository->updateMeta($postId, 'test_key', 'updated_value');
        $value = $this->repository->getMeta($postId, 'test_key', true);
        self::assertSame('updated_value', $value);

        // deleteMeta
        $deleted = $this->repository->deleteMeta($postId, 'test_key');
        self::assertTrue($deleted);

        $value = $this->repository->getMeta($postId, 'test_key', true);
        self::assertSame('', $value);

        wp_delete_post($postId, true);
    }

    #[Test]
    public function findOneByMetaReturnsIdWhenFound(): void
    {
        $postId = wp_insert_post(['post_title' => 'test-find-by-meta', 'post_status' => 'publish']);
        self::assertIsInt($postId);

        add_post_meta($postId, '_test_unique_key', 'unique_value_' . $postId);

        $result = $this->repository->findOneByMeta('_test_unique_key', 'unique_value_' . $postId, 'post', 'publish');

        self::assertSame($postId, $result);

        wp_delete_post($postId, true);
    }

    #[Test]
    public function findOneByMetaReturnsNullWhenNotFound(): void
    {
        $result = $this->repository->findOneByMeta('_nonexistent_key', 'nonexistent_value_' . uniqid());

        self::assertNull($result);
    }
}
