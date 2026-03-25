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

namespace WpPack\Component\Query\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Builder\PostQueryBuilder;
use WpPack\Component\Query\Builder\TermQueryBuilder;
use WpPack\Component\Query\Builder\UserQueryBuilder;
use WpPack\Component\Query\QueryFactory;
use WpPack\Component\Query\Result\PostQueryResult;
use WpPack\Component\Query\Result\TermQueryResult;
use WpPack\Component\Query\Result\UserQueryResult;

final class QueryFactoryTest extends TestCase
{
    // ── posts() builder type ──

    #[Test]
    public function postsReturnsPostQueryBuilder(): void
    {
        $factory = new QueryFactory();
        $builder = $factory->posts();

        self::assertInstanceOf(PostQueryBuilder::class, $builder);
    }

    #[Test]
    public function postsWithTypeSetsPostType(): void
    {
        $factory = new QueryFactory();
        $args = $factory->posts('product')->toArray();

        self::assertSame('product', $args['post_type']);
    }

    #[Test]
    public function postsWithArrayTypeSetsPostTypes(): void
    {
        $factory = new QueryFactory();
        $args = $factory->posts(['post', 'page'])->toArray();

        self::assertSame(['post', 'page'], $args['post_type']);
    }

    #[Test]
    public function postsWithNullDoesNotSetPostType(): void
    {
        $factory = new QueryFactory();
        $args = $factory->posts()->toArray();

        self::assertArrayNotHasKey('post_type', $args);
    }

    // ── users() builder type ──

    #[Test]
    public function usersReturnsUserQueryBuilder(): void
    {
        $factory = new QueryFactory();
        $builder = $factory->users();

        self::assertInstanceOf(UserQueryBuilder::class, $builder);
    }

    // ── terms() builder type ──

    #[Test]
    public function termsReturnsTermQueryBuilder(): void
    {
        $factory = new QueryFactory();
        $builder = $factory->terms();

        self::assertInstanceOf(TermQueryBuilder::class, $builder);
    }

    #[Test]
    public function termsWithTaxonomySetsTaxonomy(): void
    {
        $factory = new QueryFactory();
        $args = $factory->terms('category')->toArray();

        self::assertSame('category', $args['taxonomy']);
    }

    #[Test]
    public function termsWithArrayTaxonomySetsTaxonomies(): void
    {
        $factory = new QueryFactory();
        $args = $factory->terms(['category', 'post_tag'])->toArray();

        self::assertSame(['category', 'post_tag'], $args['taxonomy']);
    }

    #[Test]
    public function termsWithNullDoesNotSetTaxonomy(): void
    {
        $factory = new QueryFactory();
        $args = $factory->terms()->toArray();

        self::assertArrayNotHasKey('taxonomy', $args);
    }

    // ── Instance isolation ──

    #[Test]
    public function eachCallReturnsNewInstance(): void
    {
        $factory = new QueryFactory();
        $builder1 = $factory->posts('post');
        $builder2 = $factory->posts('page');

        self::assertNotSame($builder1, $builder2);
        self::assertSame('post', $builder1->toArray()['post_type']);
        self::assertSame('page', $builder2->toArray()['post_type']);
    }

    #[Test]
    public function termsCallsReturnIndependentInstances(): void
    {
        $factory = new QueryFactory();
        $builder1 = $factory->terms('category');
        $builder2 = $factory->terms('post_tag');

        self::assertNotSame($builder1, $builder2);
        self::assertSame('category', $builder1->toArray()['taxonomy']);
        self::assertSame('post_tag', $builder2->toArray()['taxonomy']);
    }

    #[Test]
    public function usersCallsReturnIndependentInstances(): void
    {
        $factory = new QueryFactory();
        $builder1 = $factory->users();
        $builder2 = $factory->users();

        self::assertNotSame($builder1, $builder2);
    }

    // ── Builder chainability from factory ──

    #[Test]
    public function postBuilderFromFactoryIsChainable(): void
    {
        $factory = new QueryFactory();
        $args = $factory->posts('post')
            ->setMaxResults(5)
            ->setFirstResult(10)
            ->search('keyword')
            ->toArray();

        self::assertSame('post', $args['post_type']);
        self::assertSame(5, $args['posts_per_page']);
        self::assertSame(10, $args['offset']);
        self::assertSame('keyword', $args['s']);
    }

    #[Test]
    public function termBuilderFromFactoryIsChainable(): void
    {
        $factory = new QueryFactory();
        $args = $factory->terms('category')
            ->hideEmpty()
            ->setMaxResults(20)
            ->toArray();

        self::assertSame('category', $args['taxonomy']);
        self::assertTrue($args['hide_empty']);
        self::assertSame(20, $args['number']);
    }

    #[Test]
    public function userBuilderFromFactoryIsChainable(): void
    {
        $factory = new QueryFactory();
        $args = $factory->users()
            ->where('u.role = :role')
            ->setParameter('role', 'editor')
            ->setMaxResults(10)
            ->toArray();

        self::assertSame('editor', $args['role']);
        self::assertSame(10, $args['number']);
    }

    // ── Integration: factory executes real queries ──

    #[Test]
    public function postsFactoryExecutesQueryAndReturnsResult(): void
    {
        $postId = wp_insert_post([
            'post_title' => 'QF post integration',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);
        self::assertIsInt($postId);

        try {
            $factory = new QueryFactory();
            $result = $factory->posts('post')
                ->where('p.id = :id')
                ->setParameter('id', $postId)
                ->get();

            self::assertInstanceOf(PostQueryResult::class, $result);
            self::assertSame(1, $result->count());
            self::assertSame($postId, $result->first()->ID);
        } finally {
            wp_delete_post($postId, true);
        }
    }

    #[Test]
    public function termsFactoryExecutesQueryAndReturnsResult(): void
    {
        $termData = wp_insert_term('QF term integration', 'category');
        self::assertIsArray($termData);
        $termId = $termData['term_id'];

        try {
            $factory = new QueryFactory();
            $result = $factory->terms('category')
                ->hideEmpty(false)
                ->where('t.id = :id')
                ->setParameter('id', $termId)
                ->get();

            self::assertInstanceOf(TermQueryResult::class, $result);
            self::assertSame(1, $result->count());
            self::assertSame($termId, $result->first()->term_id);
        } finally {
            wp_delete_term($termId, 'category');
        }
    }

    #[Test]
    public function usersFactoryExecutesQueryAndReturnsResult(): void
    {
        $userId = wp_create_user('qf_user_' . uniqid(), 'password123', 'qf_user_' . uniqid() . '@example.com');
        self::assertIsInt($userId);

        try {
            $factory = new QueryFactory();
            $result = $factory->users()
                ->where('u.id = :id')
                ->setParameter('id', $userId)
                ->get();

            self::assertInstanceOf(UserQueryResult::class, $result);
            self::assertSame(1, $result->count());
            self::assertSame($userId, $result->first()->ID);
        } finally {
            wp_delete_user($userId);
        }
    }

    // ── Integration: factory with multiple post types ──

    #[Test]
    public function postsFactoryWithMultipleTypesFindsCorrectPosts(): void
    {
        $postId = wp_insert_post([
            'post_title' => 'QF multi-type post',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);
        $pageId = wp_insert_post([
            'post_title' => 'QF multi-type page',
            'post_status' => 'publish',
            'post_type' => 'page',
        ]);
        self::assertIsInt($postId);
        self::assertIsInt($pageId);

        try {
            $factory = new QueryFactory();
            $result = $factory->posts(['post', 'page'])
                ->where('p.id IN :ids')
                ->setParameter('ids', [$postId, $pageId])
                ->get();

            self::assertInstanceOf(PostQueryResult::class, $result);
            self::assertSame(2, $result->count());
            self::assertContains($postId, $result->ids());
            self::assertContains($pageId, $result->ids());
        } finally {
            wp_delete_post($postId, true);
            wp_delete_post($pageId, true);
        }
    }

    // ── Integration: factory terms with multiple taxonomies ──

    #[Test]
    public function termsFactoryWithMultipleTaxonomiesFindsCorrectTerms(): void
    {
        $catData = wp_insert_term('QF multi-tax cat', 'category');
        $tagData = wp_insert_term('QF multi-tax tag', 'post_tag');
        self::assertIsArray($catData);
        self::assertIsArray($tagData);
        $catId = $catData['term_id'];
        $tagId = $tagData['term_id'];

        try {
            $factory = new QueryFactory();
            $result = $factory->terms(['category', 'post_tag'])
                ->hideEmpty(false)
                ->where('t.id IN :ids')
                ->setParameter('ids', [$catId, $tagId])
                ->get();

            self::assertInstanceOf(TermQueryResult::class, $result);
            self::assertSame(2, $result->count());
            self::assertContains($catId, $result->ids());
            self::assertContains($tagId, $result->ids());
        } finally {
            wp_delete_term($catId, 'category');
            wp_delete_term($tagId, 'post_tag');
        }
    }

    // ── Integration: factory returns empty results ──

    #[Test]
    public function postsFactoryReturnsEmptyResultWhenNoMatch(): void
    {
        $factory = new QueryFactory();
        $result = $factory->posts('post')
            ->where('p.id = :id')
            ->setParameter('id', 999999)
            ->get();

        self::assertInstanceOf(PostQueryResult::class, $result);
        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->count());
    }

    #[Test]
    public function termsFactoryReturnsEmptyResultWhenNoMatch(): void
    {
        $factory = new QueryFactory();
        $result = $factory->terms('category')
            ->where('t.id = :id')
            ->setParameter('id', 999999)
            ->get();

        self::assertInstanceOf(TermQueryResult::class, $result);
        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->count());
    }

    #[Test]
    public function usersFactoryReturnsEmptyResultWhenNoMatch(): void
    {
        $factory = new QueryFactory();
        $result = $factory->users()
            ->where('u.id = :id')
            ->setParameter('id', 999999)
            ->get();

        self::assertInstanceOf(UserQueryResult::class, $result);
        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->count());
    }
}
