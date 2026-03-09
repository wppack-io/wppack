<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Builder;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Builder\UserQueryBuilder;
use WpPack\Component\Query\Condition\ConditionGroup;
use WpPack\Component\Query\Enum\Order;

final class UserQueryBuilderTest extends TestCase
{
    #[Test]
    public function roleSetsRole(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->role('author')->toArray();

        self::assertSame('author', $args['role']);
    }

    #[Test]
    public function roleArraySetsRoleIn(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->role(['author', 'editor'])->toArray();

        self::assertSame(['author', 'editor'], $args['role__in']);
    }

    #[Test]
    public function roleNotInSetsExclusion(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->roleNotIn(['subscriber'])->toArray();

        self::assertSame(['subscriber'], $args['role__not_in']);
    }

    #[Test]
    public function idSetsSingleUserId(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->id(5)->toArray();

        self::assertSame([5], $args['include']);
    }

    #[Test]
    public function idSetsArrayOfUserIds(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->id([5, 10])->toArray();

        self::assertSame([5, 10], $args['include']);
    }

    #[Test]
    public function notInSetsExcludedUserIds(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->notIn([3])->toArray();

        self::assertSame([3], $args['exclude']);
    }

    #[Test]
    public function searchWrapsWithWildcards(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->search('john')->toArray();

        self::assertSame('*john*', $args['search']);
    }

    #[Test]
    public function hasPublishedPostsSetsFlag(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->hasPublishedPosts()->toArray();

        self::assertTrue($args['has_published_posts']);
    }

    #[Test]
    public function hasPublishedPostsWithPostType(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->hasPublishedPosts('product')->toArray();

        self::assertSame('product', $args['has_published_posts']);
    }

    // ── Meta conditions ──

    #[Test]
    public function whereAddsMetaCondition(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('m.company = :company')
            ->setParameter('company', 'Acme')
            ->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'company', 'value' => 'Acme', 'compare' => '='],
        ], $args['meta_query']);
    }

    #[Test]
    public function orWhereAddsOrMetaCondition(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->orWhere('m.role_type = :admin')
            ->orWhere('m.role_type = :manager')
            ->setParameter('admin', 'admin')
            ->setParameter('manager', 'manager')
            ->toArray();

        self::assertSame('OR', $args['meta_query']['relation']);
    }

    #[Test]
    public function nestedWhereWithClosure(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('m.active = :active')
            ->andWhere(function (ConditionGroup $group): void {
                $group->where('m.department = :eng')
                    ->orWhere('m.department = :design');
            })
            ->setParameter('active', true)
            ->setParameter('eng', 'engineering')
            ->setParameter('design', 'design')
            ->toArray();

        self::assertSame('AND', $args['meta_query']['relation']);
    }

    #[Test]
    public function taxPrefixIsRejected(): void
    {
        $builder = new UserQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefix "tax" is not allowed');

        $builder->where('t.category IN :cats');
    }

    // ── Ordering ──

    #[Test]
    public function orderBySetsOrderByAndOrder(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->orderBy('display_name', Order::Asc)->toArray();

        self::assertSame('display_name', $args['orderby']);
        self::assertSame('ASC', $args['order']);
    }

    #[Test]
    public function orderByDefaultsToAsc(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->orderBy('registered')->toArray();

        self::assertSame('ASC', $args['order']);
    }

    // ── Pagination ──

    #[Test]
    public function limitSetsNumber(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->limit(20)->toArray();

        self::assertSame(20, $args['number']);
    }

    #[Test]
    public function pageSetsPaged(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->page(3)->toArray();

        self::assertSame(3, $args['paged']);
    }

    #[Test]
    public function offsetSetsOffset(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->offset(10)->toArray();

        self::assertSame(10, $args['offset']);
    }

    // ── Escape hatch ──

    #[Test]
    public function argSetsArbitraryArgument(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->arg('blog_id', 2)->toArray();

        self::assertSame(2, $args['blog_id']);
    }

    // ── Complex queries ──

    #[Test]
    public function complexQueryBuildsCorrectly(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->role('author')
            ->where('m.company = :company')
            ->setParameter('company', 'Acme')
            ->orderBy('display_name')
            ->limit(10)
            ->page(2)
            ->toArray();

        self::assertSame('author', $args['role']);
        self::assertArrayHasKey('meta_query', $args);
        self::assertSame('display_name', $args['orderby']);
        self::assertSame(10, $args['number']);
        self::assertSame(2, $args['paged']);
    }

    #[Test]
    public function emptyBuilderReturnsEmptyArgs(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->toArray();

        self::assertSame([], $args);
    }

    #[Test]
    public function noMetaQueryWhenNoConditions(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->role('author')->toArray();

        self::assertArrayNotHasKey('meta_query', $args);
    }

    // ── Execution methods (WordPress integration) ──

    #[Test]
    public function getReturnsUserQueryResult(): void
    {
        if (!class_exists(\WP_User_Query::class)) {
            self::markTestSkipped('WordPress is not available.');
        }

        $result = (new UserQueryBuilder())
            ->role('author')
            ->limit(5)
            ->get();

        self::assertInstanceOf(\WpPack\Component\Query\Result\UserQueryResult::class, $result);
    }

    #[Test]
    public function firstReturnsNullableWpUser(): void
    {
        if (!class_exists(\WP_User_Query::class)) {
            self::markTestSkipped('WordPress is not available.');
        }

        $user = (new UserQueryBuilder())
            ->id(999999)
            ->first();

        self::assertNull($user);
    }
}
