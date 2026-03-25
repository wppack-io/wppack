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

namespace WpPack\Component\Query\Tests\Result;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Result\PostQueryResult;
use WpPack\Component\Query\Result\TermQueryResult;
use WpPack\Component\Query\Result\UserQueryResult;

final class QueryResultTest extends TestCase
{
    // ══════════════════════════════════════════════
    // PostQueryResult
    // ══════════════════════════════════════════════

    #[Test]
    public function postQueryResultAllReturnsAllPosts(): void
    {
        $postId1 = wp_insert_post(['post_title' => 'PQR all test 1', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId2 = wp_insert_post(['post_title' => 'PQR all test 2', 'post_status' => 'publish', 'post_type' => 'post']);
        self::assertIsInt($postId1);
        self::assertIsInt($postId2);

        try {
            $query = new \WP_Query([
                'post__in' => [$postId1, $postId2],
                'post_type' => 'post',
                'post_status' => 'publish',
                'orderby' => 'post__in',
            ]);

            $result = new PostQueryResult(
                posts: $query->posts,
                total: $query->found_posts,
                totalPages: (int) $query->max_num_pages,
                currentPage: 1,
                wpQueryInstance: $query,
            );

            $all = $result->all();
            self::assertCount(2, $all);
            self::assertInstanceOf(\WP_Post::class, $all[0]);
            self::assertInstanceOf(\WP_Post::class, $all[1]);
        } finally {
            wp_delete_post($postId1, true);
            wp_delete_post($postId2, true);
        }
    }

    #[Test]
    public function postQueryResultFirstReturnsFirstPost(): void
    {
        $postId = wp_insert_post(['post_title' => 'PQR first test', 'post_status' => 'publish', 'post_type' => 'post']);
        self::assertIsInt($postId);

        try {
            $query = new \WP_Query([
                'post__in' => [$postId],
                'post_type' => 'post',
                'post_status' => 'publish',
            ]);

            $result = new PostQueryResult(
                posts: $query->posts,
                total: $query->found_posts,
                totalPages: (int) $query->max_num_pages,
                currentPage: 1,
                wpQueryInstance: $query,
            );

            $first = $result->first();
            self::assertInstanceOf(\WP_Post::class, $first);
            self::assertSame($postId, $first->ID);
        } finally {
            wp_delete_post($postId, true);
        }
    }

    #[Test]
    public function postQueryResultFirstReturnsNullWhenEmpty(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpQueryInstance: $query,
        );

        self::assertNull($result->first());
    }

    #[Test]
    public function postQueryResultIsEmptyReturnsTrueWhenNoPosts(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpQueryInstance: $query,
        );

        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function postQueryResultIsEmptyReturnsFalseWhenPostsExist(): void
    {
        $postId = wp_insert_post(['post_title' => 'PQR isEmpty test', 'post_status' => 'publish', 'post_type' => 'post']);
        self::assertIsInt($postId);

        try {
            $query = new \WP_Query([
                'post__in' => [$postId],
                'post_type' => 'post',
                'post_status' => 'publish',
            ]);

            $result = new PostQueryResult(
                posts: $query->posts,
                total: $query->found_posts,
                totalPages: (int) $query->max_num_pages,
                currentPage: 1,
                wpQueryInstance: $query,
            );

            self::assertFalse($result->isEmpty());
        } finally {
            wp_delete_post($postId, true);
        }
    }

    #[Test]
    public function postQueryResultCountReturnsNumberOfPosts(): void
    {
        $postId1 = wp_insert_post(['post_title' => 'PQR count 1', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId2 = wp_insert_post(['post_title' => 'PQR count 2', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId3 = wp_insert_post(['post_title' => 'PQR count 3', 'post_status' => 'publish', 'post_type' => 'post']);
        self::assertIsInt($postId1);
        self::assertIsInt($postId2);
        self::assertIsInt($postId3);

        try {
            $query = new \WP_Query([
                'post__in' => [$postId1, $postId2, $postId3],
                'post_type' => 'post',
                'post_status' => 'publish',
            ]);

            $result = new PostQueryResult(
                posts: $query->posts,
                total: $query->found_posts,
                totalPages: (int) $query->max_num_pages,
                currentPage: 1,
                wpQueryInstance: $query,
            );

            self::assertSame(3, $result->count());
            self::assertSame(3, \count($result));
        } finally {
            wp_delete_post($postId1, true);
            wp_delete_post($postId2, true);
            wp_delete_post($postId3, true);
        }
    }

    #[Test]
    public function postQueryResultCountReturnsZeroWhenEmpty(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpQueryInstance: $query,
        );

        self::assertSame(0, $result->count());
        self::assertSame(0, \count($result));
    }

    #[Test]
    public function postQueryResultIdsReturnsPostIds(): void
    {
        $postId1 = wp_insert_post(['post_title' => 'PQR ids 1', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId2 = wp_insert_post(['post_title' => 'PQR ids 2', 'post_status' => 'publish', 'post_type' => 'post']);
        self::assertIsInt($postId1);
        self::assertIsInt($postId2);

        try {
            $query = new \WP_Query([
                'post__in' => [$postId1, $postId2],
                'post_type' => 'post',
                'post_status' => 'publish',
                'orderby' => 'post__in',
            ]);

            $result = new PostQueryResult(
                posts: $query->posts,
                total: $query->found_posts,
                totalPages: (int) $query->max_num_pages,
                currentPage: 1,
                wpQueryInstance: $query,
            );

            $ids = $result->ids();
            self::assertSame([$postId1, $postId2], $ids);
        } finally {
            wp_delete_post($postId1, true);
            wp_delete_post($postId2, true);
        }
    }

    #[Test]
    public function postQueryResultIdsReturnsEmptyArrayWhenNoPosts(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpQueryInstance: $query,
        );

        self::assertSame([], $result->ids());
    }

    #[Test]
    public function postQueryResultHasNextPageReturnsTrueWhenMorePages(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 30,
            totalPages: 3,
            currentPage: 1,
            wpQueryInstance: $query,
        );

        self::assertTrue($result->hasNextPage());
    }

    #[Test]
    public function postQueryResultHasNextPageReturnsFalseOnLastPage(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 30,
            totalPages: 3,
            currentPage: 3,
            wpQueryInstance: $query,
        );

        self::assertFalse($result->hasNextPage());
    }

    #[Test]
    public function postQueryResultHasNextPageReturnsFalseWhenSinglePage(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 5,
            totalPages: 1,
            currentPage: 1,
            wpQueryInstance: $query,
        );

        self::assertFalse($result->hasNextPage());
    }

    #[Test]
    public function postQueryResultIsIterable(): void
    {
        $postId1 = wp_insert_post(['post_title' => 'PQR iter 1', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId2 = wp_insert_post(['post_title' => 'PQR iter 2', 'post_status' => 'publish', 'post_type' => 'post']);
        self::assertIsInt($postId1);
        self::assertIsInt($postId2);

        try {
            $query = new \WP_Query([
                'post__in' => [$postId1, $postId2],
                'post_type' => 'post',
                'post_status' => 'publish',
                'orderby' => 'post__in',
            ]);

            $result = new PostQueryResult(
                posts: $query->posts,
                total: $query->found_posts,
                totalPages: (int) $query->max_num_pages,
                currentPage: 1,
                wpQueryInstance: $query,
            );

            $collected = [];
            foreach ($result as $post) {
                $collected[] = $post->ID;
            }

            self::assertSame([$postId1, $postId2], $collected);
        } finally {
            wp_delete_post($postId1, true);
            wp_delete_post($postId2, true);
        }
    }

    #[Test]
    public function postQueryResultGetIteratorReturnsArrayIterator(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpQueryInstance: $query,
        );

        self::assertInstanceOf(\ArrayIterator::class, $result->getIterator());
    }

    #[Test]
    public function postQueryResultWpQueryReturnsUnderlyingInstance(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpQueryInstance: $query,
        );

        self::assertSame($query, $result->wpQuery());
    }

    #[Test]
    public function postQueryResultPublicReadonlyProperties(): void
    {
        $query = new \WP_Query([
            'post__in' => [0],
            'post_type' => 'post',
        ]);

        $result = new PostQueryResult(
            posts: [],
            total: 42,
            totalPages: 5,
            currentPage: 2,
            wpQueryInstance: $query,
        );

        self::assertSame(42, $result->total);
        self::assertSame(5, $result->totalPages);
        self::assertSame(2, $result->currentPage);
    }

    #[Test]
    public function postQueryResultIntegrationViaBuilder(): void
    {
        $postId1 = wp_insert_post(['post_title' => 'PQR builder 1', 'post_status' => 'publish', 'post_type' => 'post']);
        $postId2 = wp_insert_post(['post_title' => 'PQR builder 2', 'post_status' => 'publish', 'post_type' => 'post']);
        self::assertIsInt($postId1);
        self::assertIsInt($postId2);

        try {
            $result = (new \WpPack\Component\Query\Builder\PostQueryBuilder())
                ->where('p.id IN :ids')
                ->setParameter('ids', [$postId1, $postId2])
                ->get();

            self::assertInstanceOf(PostQueryResult::class, $result);
            self::assertSame(2, $result->count());
            self::assertFalse($result->isEmpty());
            self::assertContains($postId1, $result->ids());
            self::assertContains($postId2, $result->ids());
            self::assertSame(2, $result->total);
        } finally {
            wp_delete_post($postId1, true);
            wp_delete_post($postId2, true);
        }
    }

    // ══════════════════════════════════════════════
    // TermQueryResult
    // ══════════════════════════════════════════════

    #[Test]
    public function termQueryResultAllReturnsAllTerms(): void
    {
        $termId1 = wp_insert_term('TQR all test 1', 'category');
        $termId2 = wp_insert_term('TQR all test 2', 'category');
        self::assertIsArray($termId1);
        self::assertIsArray($termId2);
        $tid1 = $termId1['term_id'];
        $tid2 = $termId2['term_id'];

        try {
            $query = new \WP_Term_Query([
                'taxonomy' => 'category',
                'include' => [$tid1, $tid2],
                'hide_empty' => false,
            ]);
            /** @var list<\WP_Term> $terms */
            $terms = $query->get_terms();

            $result = new TermQueryResult(
                terms: $terms,
                total: \count($terms),
                wpTermQuery: $query,
            );

            $all = $result->all();
            self::assertCount(2, $all);
            self::assertInstanceOf(\WP_Term::class, $all[0]);
            self::assertInstanceOf(\WP_Term::class, $all[1]);
        } finally {
            wp_delete_term($tid1, 'category');
            wp_delete_term($tid2, 'category');
        }
    }

    #[Test]
    public function termQueryResultFirstReturnsFirstTerm(): void
    {
        $termId = wp_insert_term('TQR first test', 'category');
        self::assertIsArray($termId);
        $tid = $termId['term_id'];

        try {
            $query = new \WP_Term_Query([
                'taxonomy' => 'category',
                'include' => [$tid],
                'hide_empty' => false,
            ]);
            /** @var list<\WP_Term> $terms */
            $terms = $query->get_terms();

            $result = new TermQueryResult(
                terms: $terms,
                total: \count($terms),
                wpTermQuery: $query,
            );

            $first = $result->first();
            self::assertInstanceOf(\WP_Term::class, $first);
            self::assertSame($tid, $first->term_id);
        } finally {
            wp_delete_term($tid, 'category');
        }
    }

    #[Test]
    public function termQueryResultFirstReturnsNullWhenEmpty(): void
    {
        $query = new \WP_Term_Query([
            'taxonomy' => 'category',
            'include' => [0],
            'hide_empty' => false,
        ]);

        $result = new TermQueryResult(
            terms: [],
            total: 0,
            wpTermQuery: $query,
        );

        self::assertNull($result->first());
    }

    #[Test]
    public function termQueryResultIsEmptyReturnsTrueWhenNoTerms(): void
    {
        $query = new \WP_Term_Query([
            'taxonomy' => 'category',
            'include' => [0],
            'hide_empty' => false,
        ]);

        $result = new TermQueryResult(
            terms: [],
            total: 0,
            wpTermQuery: $query,
        );

        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function termQueryResultIsEmptyReturnsFalseWhenTermsExist(): void
    {
        $termId = wp_insert_term('TQR isEmpty test', 'category');
        self::assertIsArray($termId);
        $tid = $termId['term_id'];

        try {
            $query = new \WP_Term_Query([
                'taxonomy' => 'category',
                'include' => [$tid],
                'hide_empty' => false,
            ]);
            /** @var list<\WP_Term> $terms */
            $terms = $query->get_terms();

            $result = new TermQueryResult(
                terms: $terms,
                total: \count($terms),
                wpTermQuery: $query,
            );

            self::assertFalse($result->isEmpty());
        } finally {
            wp_delete_term($tid, 'category');
        }
    }

    #[Test]
    public function termQueryResultCountReturnsNumberOfTerms(): void
    {
        $termId1 = wp_insert_term('TQR count 1', 'category');
        $termId2 = wp_insert_term('TQR count 2', 'category');
        self::assertIsArray($termId1);
        self::assertIsArray($termId2);
        $tid1 = $termId1['term_id'];
        $tid2 = $termId2['term_id'];

        try {
            $query = new \WP_Term_Query([
                'taxonomy' => 'category',
                'include' => [$tid1, $tid2],
                'hide_empty' => false,
            ]);
            /** @var list<\WP_Term> $terms */
            $terms = $query->get_terms();

            $result = new TermQueryResult(
                terms: $terms,
                total: 10,
                wpTermQuery: $query,
            );

            self::assertSame(2, $result->count());
            self::assertSame(2, \count($result));
            // total is the overall count (may differ from current page count)
            self::assertSame(10, $result->total);
        } finally {
            wp_delete_term($tid1, 'category');
            wp_delete_term($tid2, 'category');
        }
    }

    #[Test]
    public function termQueryResultCountReturnsZeroWhenEmpty(): void
    {
        $query = new \WP_Term_Query([
            'taxonomy' => 'category',
            'include' => [0],
            'hide_empty' => false,
        ]);

        $result = new TermQueryResult(
            terms: [],
            total: 0,
            wpTermQuery: $query,
        );

        self::assertSame(0, $result->count());
        self::assertSame(0, \count($result));
    }

    #[Test]
    public function termQueryResultIdsReturnsTermIds(): void
    {
        $termId1 = wp_insert_term('TQR ids 1', 'category');
        $termId2 = wp_insert_term('TQR ids 2', 'category');
        self::assertIsArray($termId1);
        self::assertIsArray($termId2);
        $tid1 = $termId1['term_id'];
        $tid2 = $termId2['term_id'];

        try {
            $query = new \WP_Term_Query([
                'taxonomy' => 'category',
                'include' => [$tid1, $tid2],
                'hide_empty' => false,
                'orderby' => 'include',
            ]);
            /** @var list<\WP_Term> $terms */
            $terms = $query->get_terms();

            $result = new TermQueryResult(
                terms: $terms,
                total: \count($terms),
                wpTermQuery: $query,
            );

            self::assertSame([$tid1, $tid2], $result->ids());
        } finally {
            wp_delete_term($tid1, 'category');
            wp_delete_term($tid2, 'category');
        }
    }

    #[Test]
    public function termQueryResultIdsReturnsEmptyArrayWhenNoTerms(): void
    {
        $query = new \WP_Term_Query([
            'taxonomy' => 'category',
            'include' => [0],
            'hide_empty' => false,
        ]);

        $result = new TermQueryResult(
            terms: [],
            total: 0,
            wpTermQuery: $query,
        );

        self::assertSame([], $result->ids());
    }

    #[Test]
    public function termQueryResultIsIterable(): void
    {
        $termId1 = wp_insert_term('TQR iter 1', 'category');
        $termId2 = wp_insert_term('TQR iter 2', 'category');
        self::assertIsArray($termId1);
        self::assertIsArray($termId2);
        $tid1 = $termId1['term_id'];
        $tid2 = $termId2['term_id'];

        try {
            $query = new \WP_Term_Query([
                'taxonomy' => 'category',
                'include' => [$tid1, $tid2],
                'hide_empty' => false,
                'orderby' => 'include',
            ]);
            /** @var list<\WP_Term> $terms */
            $terms = $query->get_terms();

            $result = new TermQueryResult(
                terms: $terms,
                total: \count($terms),
                wpTermQuery: $query,
            );

            $collected = [];
            foreach ($result as $term) {
                $collected[] = $term->term_id;
            }

            self::assertSame([$tid1, $tid2], $collected);
        } finally {
            wp_delete_term($tid1, 'category');
            wp_delete_term($tid2, 'category');
        }
    }

    #[Test]
    public function termQueryResultGetIteratorReturnsArrayIterator(): void
    {
        $query = new \WP_Term_Query([
            'taxonomy' => 'category',
            'include' => [0],
            'hide_empty' => false,
        ]);

        $result = new TermQueryResult(
            terms: [],
            total: 0,
            wpTermQuery: $query,
        );

        self::assertInstanceOf(\ArrayIterator::class, $result->getIterator());
    }

    #[Test]
    public function termQueryResultWpTermQueryReturnsUnderlyingInstance(): void
    {
        $query = new \WP_Term_Query([
            'taxonomy' => 'category',
            'include' => [0],
            'hide_empty' => false,
        ]);

        $result = new TermQueryResult(
            terms: [],
            total: 0,
            wpTermQuery: $query,
        );

        self::assertSame($query, $result->wpTermQuery());
    }

    #[Test]
    public function termQueryResultPublicReadonlyProperties(): void
    {
        $query = new \WP_Term_Query([
            'taxonomy' => 'category',
            'include' => [0],
            'hide_empty' => false,
        ]);

        $result = new TermQueryResult(
            terms: [],
            total: 15,
            wpTermQuery: $query,
        );

        self::assertSame(15, $result->total);
    }

    #[Test]
    public function termQueryResultIntegrationViaBuilder(): void
    {
        $termId1 = wp_insert_term('TQR builder 1', 'category');
        $termId2 = wp_insert_term('TQR builder 2', 'category');
        self::assertIsArray($termId1);
        self::assertIsArray($termId2);
        $tid1 = $termId1['term_id'];
        $tid2 = $termId2['term_id'];

        try {
            $result = (new \WpPack\Component\Query\Builder\TermQueryBuilder())
                ->hideEmpty(false)
                ->where('t.taxonomy = :tax')
                ->andWhere('t.id IN :ids')
                ->setParameters(['tax' => 'category', 'ids' => [$tid1, $tid2]])
                ->get();

            self::assertInstanceOf(TermQueryResult::class, $result);
            self::assertSame(2, $result->count());
            self::assertFalse($result->isEmpty());
            self::assertContains($tid1, $result->ids());
            self::assertContains($tid2, $result->ids());
        } finally {
            wp_delete_term($tid1, 'category');
            wp_delete_term($tid2, 'category');
        }
    }

    // ══════════════════════════════════════════════
    // UserQueryResult
    // ══════════════════════════════════════════════

    #[Test]
    public function userQueryResultAllReturnsAllUsers(): void
    {
        $userId1 = wp_create_user('uqr_all_1_' . uniqid(), 'password123', 'uqr_all1_' . uniqid() . '@example.com');
        $userId2 = wp_create_user('uqr_all_2_' . uniqid(), 'password123', 'uqr_all2_' . uniqid() . '@example.com');
        self::assertIsInt($userId1);
        self::assertIsInt($userId2);

        try {
            $query = new \WP_User_Query([
                'include' => [$userId1, $userId2],
            ]);

            /** @var list<\WP_User> $users */
            $users = $query->get_results();

            $result = new UserQueryResult(
                users: $users,
                total: $query->get_total(),
                totalPages: 1,
                currentPage: 1,
                wpUserQuery: $query,
            );

            $all = $result->all();
            self::assertCount(2, $all);
            self::assertInstanceOf(\WP_User::class, $all[0]);
            self::assertInstanceOf(\WP_User::class, $all[1]);
        } finally {
            wp_delete_user($userId1);
            wp_delete_user($userId2);
        }
    }

    #[Test]
    public function userQueryResultFirstReturnsFirstUser(): void
    {
        $userId = wp_create_user('uqr_first_' . uniqid(), 'password123', 'uqr_first_' . uniqid() . '@example.com');
        self::assertIsInt($userId);

        try {
            $query = new \WP_User_Query([
                'include' => [$userId],
            ]);

            /** @var list<\WP_User> $users */
            $users = $query->get_results();

            $result = new UserQueryResult(
                users: $users,
                total: $query->get_total(),
                totalPages: 1,
                currentPage: 1,
                wpUserQuery: $query,
            );

            $first = $result->first();
            self::assertInstanceOf(\WP_User::class, $first);
            self::assertSame($userId, $first->ID);
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function userQueryResultFirstReturnsNullWhenEmpty(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpUserQuery: $query,
        );

        self::assertNull($result->first());
    }

    #[Test]
    public function userQueryResultIsEmptyReturnsTrueWhenNoUsers(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpUserQuery: $query,
        );

        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function userQueryResultIsEmptyReturnsFalseWhenUsersExist(): void
    {
        $userId = wp_create_user('uqr_empty_' . uniqid(), 'password123', 'uqr_empty_' . uniqid() . '@example.com');
        self::assertIsInt($userId);

        try {
            $query = new \WP_User_Query([
                'include' => [$userId],
            ]);

            /** @var list<\WP_User> $users */
            $users = $query->get_results();

            $result = new UserQueryResult(
                users: $users,
                total: $query->get_total(),
                totalPages: 1,
                currentPage: 1,
                wpUserQuery: $query,
            );

            self::assertFalse($result->isEmpty());
        } finally {
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function userQueryResultCountReturnsNumberOfUsers(): void
    {
        $userId1 = wp_create_user('uqr_count_1_' . uniqid(), 'password123', 'uqr_count1_' . uniqid() . '@example.com');
        $userId2 = wp_create_user('uqr_count_2_' . uniqid(), 'password123', 'uqr_count2_' . uniqid() . '@example.com');
        self::assertIsInt($userId1);
        self::assertIsInt($userId2);

        try {
            $query = new \WP_User_Query([
                'include' => [$userId1, $userId2],
            ]);

            /** @var list<\WP_User> $users */
            $users = $query->get_results();

            $result = new UserQueryResult(
                users: $users,
                total: $query->get_total(),
                totalPages: 1,
                currentPage: 1,
                wpUserQuery: $query,
            );

            self::assertSame(2, $result->count());
            self::assertSame(2, \count($result));
        } finally {
            wp_delete_user($userId1);
            wp_delete_user($userId2);
        }
    }

    #[Test]
    public function userQueryResultCountReturnsZeroWhenEmpty(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpUserQuery: $query,
        );

        self::assertSame(0, $result->count());
        self::assertSame(0, \count($result));
    }

    #[Test]
    public function userQueryResultIdsReturnsUserIds(): void
    {
        $userId1 = wp_create_user('uqr_ids_1_' . uniqid(), 'password123', 'uqr_ids1_' . uniqid() . '@example.com');
        $userId2 = wp_create_user('uqr_ids_2_' . uniqid(), 'password123', 'uqr_ids2_' . uniqid() . '@example.com');
        self::assertIsInt($userId1);
        self::assertIsInt($userId2);

        try {
            $query = new \WP_User_Query([
                'include' => [$userId1, $userId2],
                'orderby' => 'include',
            ]);

            /** @var list<\WP_User> $users */
            $users = $query->get_results();

            $result = new UserQueryResult(
                users: $users,
                total: $query->get_total(),
                totalPages: 1,
                currentPage: 1,
                wpUserQuery: $query,
            );

            $ids = $result->ids();
            self::assertSame([$userId1, $userId2], $ids);
        } finally {
            wp_delete_user($userId1);
            wp_delete_user($userId2);
        }
    }

    #[Test]
    public function userQueryResultIdsReturnsEmptyArrayWhenNoUsers(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpUserQuery: $query,
        );

        self::assertSame([], $result->ids());
    }

    #[Test]
    public function userQueryResultHasNextPageReturnsTrueWhenMorePages(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 50,
            totalPages: 5,
            currentPage: 2,
            wpUserQuery: $query,
        );

        self::assertTrue($result->hasNextPage());
    }

    #[Test]
    public function userQueryResultHasNextPageReturnsFalseOnLastPage(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 50,
            totalPages: 5,
            currentPage: 5,
            wpUserQuery: $query,
        );

        self::assertFalse($result->hasNextPage());
    }

    #[Test]
    public function userQueryResultHasNextPageReturnsFalseWhenSinglePage(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 3,
            totalPages: 1,
            currentPage: 1,
            wpUserQuery: $query,
        );

        self::assertFalse($result->hasNextPage());
    }

    #[Test]
    public function userQueryResultIsIterable(): void
    {
        $userId1 = wp_create_user('uqr_iter_1_' . uniqid(), 'password123', 'uqr_iter1_' . uniqid() . '@example.com');
        $userId2 = wp_create_user('uqr_iter_2_' . uniqid(), 'password123', 'uqr_iter2_' . uniqid() . '@example.com');
        self::assertIsInt($userId1);
        self::assertIsInt($userId2);

        try {
            $query = new \WP_User_Query([
                'include' => [$userId1, $userId2],
                'orderby' => 'include',
            ]);

            /** @var list<\WP_User> $users */
            $users = $query->get_results();

            $result = new UserQueryResult(
                users: $users,
                total: $query->get_total(),
                totalPages: 1,
                currentPage: 1,
                wpUserQuery: $query,
            );

            $collected = [];
            foreach ($result as $user) {
                $collected[] = $user->ID;
            }

            self::assertSame([$userId1, $userId2], $collected);
        } finally {
            wp_delete_user($userId1);
            wp_delete_user($userId2);
        }
    }

    #[Test]
    public function userQueryResultGetIteratorReturnsArrayIterator(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpUserQuery: $query,
        );

        self::assertInstanceOf(\ArrayIterator::class, $result->getIterator());
    }

    #[Test]
    public function userQueryResultWpUserQueryReturnsUnderlyingInstance(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 0,
            totalPages: 0,
            currentPage: 1,
            wpUserQuery: $query,
        );

        self::assertSame($query, $result->wpUserQuery());
    }

    #[Test]
    public function userQueryResultPublicReadonlyProperties(): void
    {
        $query = new \WP_User_Query([
            'include' => [0],
        ]);

        $result = new UserQueryResult(
            users: [],
            total: 100,
            totalPages: 10,
            currentPage: 3,
            wpUserQuery: $query,
        );

        self::assertSame(100, $result->total);
        self::assertSame(10, $result->totalPages);
        self::assertSame(3, $result->currentPage);
    }

    #[Test]
    public function userQueryResultIntegrationViaBuilder(): void
    {
        $userId1 = wp_create_user('uqr_build_1_' . uniqid(), 'password123', 'uqr_build1_' . uniqid() . '@example.com');
        $userId2 = wp_create_user('uqr_build_2_' . uniqid(), 'password123', 'uqr_build2_' . uniqid() . '@example.com');
        self::assertIsInt($userId1);
        self::assertIsInt($userId2);

        try {
            $result = (new \WpPack\Component\Query\Builder\UserQueryBuilder())
                ->where('u.id IN :ids')
                ->setParameter('ids', [$userId1, $userId2])
                ->get();

            self::assertInstanceOf(UserQueryResult::class, $result);
            self::assertSame(2, $result->count());
            self::assertFalse($result->isEmpty());
            self::assertContains($userId1, $result->ids());
            self::assertContains($userId2, $result->ids());
            self::assertSame(2, $result->total);
        } finally {
            wp_delete_user($userId1);
            wp_delete_user($userId2);
        }
    }

    // ══════════════════════════════════════════════
    // TermQueryResult has no hasNextPage (no pagination)
    // ══════════════════════════════════════════════

    #[Test]
    public function termQueryResultDoesNotHaveHasNextPage(): void
    {
        self::assertFalse(method_exists(TermQueryResult::class, 'hasNextPage'));
    }

    // ══════════════════════════════════════════════
    // Countable contract
    // ══════════════════════════════════════════════

    #[Test]
    public function postQueryResultImplementsCountable(): void
    {
        self::assertTrue(is_subclass_of(PostQueryResult::class, \Countable::class));
    }

    #[Test]
    public function termQueryResultImplementsCountable(): void
    {
        self::assertTrue(is_subclass_of(TermQueryResult::class, \Countable::class));
    }

    #[Test]
    public function userQueryResultImplementsCountable(): void
    {
        self::assertTrue(is_subclass_of(UserQueryResult::class, \Countable::class));
    }

    // ══════════════════════════════════════════════
    // IteratorAggregate contract
    // ══════════════════════════════════════════════

    #[Test]
    public function postQueryResultImplementsIteratorAggregate(): void
    {
        self::assertTrue(is_subclass_of(PostQueryResult::class, \IteratorAggregate::class));
    }

    #[Test]
    public function termQueryResultImplementsIteratorAggregate(): void
    {
        self::assertTrue(is_subclass_of(TermQueryResult::class, \IteratorAggregate::class));
    }

    #[Test]
    public function userQueryResultImplementsIteratorAggregate(): void
    {
        self::assertTrue(is_subclass_of(UserQueryResult::class, \IteratorAggregate::class));
    }
}
