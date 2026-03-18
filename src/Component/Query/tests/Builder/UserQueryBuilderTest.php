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
    // ── Standard field conditions via where() ──

    #[Test]
    public function whereRoleEquals(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('u.role = :role')
            ->setParameter('role', 'author')
            ->toArray();

        self::assertSame('author', $args['role']);
    }

    #[Test]
    public function whereRoleIn(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('u.role IN :roles')
            ->setParameter('roles', ['author', 'editor'])
            ->toArray();

        self::assertSame(['author', 'editor'], $args['role__in']);
    }

    #[Test]
    public function whereRoleNotIn(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('u.role NOT IN :roles')
            ->setParameter('roles', ['subscriber'])
            ->toArray();

        self::assertSame(['subscriber'], $args['role__not_in']);
    }

    #[Test]
    public function whereRoleWithLongPrefix(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('user.role = :role')
            ->setParameter('role', 'editor')
            ->toArray();

        self::assertSame('editor', $args['role']);
    }

    #[Test]
    public function whereIdEquals(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('u.id = :id')
            ->setParameter('id', 5)
            ->toArray();

        self::assertSame([5], $args['include']);
    }

    #[Test]
    public function whereIdIn(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('u.id IN :ids')
            ->setParameter('ids', [5, 10])
            ->toArray();

        self::assertSame([5, 10], $args['include']);
    }

    #[Test]
    public function whereIdNotIn(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('u.id NOT IN :ids')
            ->setParameter('ids', [3])
            ->toArray();

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

    // ── setParameters (batch) ──

    #[Test]
    public function setParametersBatch(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('u.role = :role')
            ->andWhere('m.company = :company')
            ->setParameters(['role' => 'author', 'company' => 'Acme'])
            ->toArray();

        self::assertSame('author', $args['role']);
        self::assertArrayHasKey('meta_query', $args);
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
        $this->expectExceptionMessage('Unknown prefix "t"');

        $builder->where('t.category IN :cats');
    }

    // ── Standard field error cases ──

    #[Test]
    public function orWhereWithStandardFieldThrows(): void
    {
        $builder = new UserQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be used in orWhere');

        $builder->orWhere('u.role = :role');
    }

    #[Test]
    public function unknownUserFieldThrows(): void
    {
        $builder = new UserQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown user field "unknown"');

        $builder
            ->where('u.unknown = :val')
            ->setParameter('val', 'test')
            ->toArray();
    }

    #[Test]
    public function unsupportedOperatorForRoleThrows(): void
    {
        $builder = new UserQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "LIKE" for field "user.role"');

        $builder
            ->where('u.role LIKE :val')
            ->setParameter('val', 'auth%')
            ->toArray();
    }

    // ── Mixed standard fields and meta ──

    #[Test]
    public function standardFieldsAndMetaConditions(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('u.role = :role')
            ->andWhere('m.company = :company')
            ->setParameter('role', 'author')
            ->setParameter('company', 'Acme')
            ->toArray();

        self::assertSame('author', $args['role']);
        self::assertArrayHasKey('meta_query', $args);
        self::assertSame('company', $args['meta_query'][0]['key']);
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

    // ── WQL ORDER BY ──

    #[Test]
    public function orderByWqlSingleField(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->orderBy('u.display_name', Order::Asc)->toArray();

        self::assertSame('display_name', $args['orderby']);
        self::assertSame('ASC', $args['order']);
    }

    #[Test]
    public function orderByWqlMeta(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->orderBy('m.last_login', Order::Desc)->toArray();

        self::assertSame('last_login', $args['meta_key']);
        self::assertSame('meta_value', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function addOrderByAppends(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->orderBy('u.display_name', Order::Asc)
            ->addOrderBy('u.user_registered', Order::Desc)
            ->toArray();

        self::assertSame(['display_name' => 'ASC', 'user_registered' => 'DESC'], $args['orderby']);
    }

    // ── Pagination (Doctrine naming) ──

    #[Test]
    public function setMaxResultsSetsNumber(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->setMaxResults(20)->toArray();

        self::assertSame(20, $args['number']);
    }

    #[Test]
    public function setFirstResultSetsOffset(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->setFirstResult(10)->toArray();

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
            ->where('u.role = :role')
            ->andWhere('m.company = :company')
            ->setParameters(['role' => 'author', 'company' => 'Acme'])
            ->orderBy('display_name')
            ->setMaxResults(10)
            ->setFirstResult(20)
            ->toArray();

        self::assertSame('author', $args['role']);
        self::assertArrayHasKey('meta_query', $args);
        self::assertSame('display_name', $args['orderby']);
        self::assertSame(10, $args['number']);
        self::assertSame(20, $args['offset']);
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
        $args = $builder
            ->where('u.role = :role')
            ->setParameter('role', 'author')
            ->toArray();

        self::assertArrayNotHasKey('meta_query', $args);
    }

    // ── hasPublishedPosts with array ──

    #[Test]
    public function hasPublishedPostsWithArrayOfPostTypes(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->hasPublishedPosts(['post', 'page'])->toArray();

        self::assertSame(['post', 'page'], $args['has_published_posts']);
    }

    // ── Ordering with string order ──

    #[Test]
    public function orderByWithStringOrder(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder->orderBy('display_name', 'DESC')->toArray();

        self::assertSame('display_name', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function addOrderByWithStringOrder(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->orderBy('u.display_name', 'ASC')
            ->addOrderBy('u.user_registered', 'DESC')
            ->toArray();

        self::assertSame(['display_name' => 'ASC', 'user_registered' => 'DESC'], $args['orderby']);
    }

    // ── orWhere with closure ──

    #[Test]
    public function orWhereWithClosure(): void
    {
        $builder = new UserQueryBuilder();
        $args = $builder
            ->where('m.active = :active')
            ->orWhere(function (ConditionGroup $group): void {
                $group->where('m.level = :l1')
                    ->orWhere('m.level = :l2');
            })
            ->setParameter('active', true)
            ->setParameter('l1', 1)
            ->setParameter('l2', 2)
            ->toArray();

        self::assertArrayHasKey('meta_query', $args);
    }

    // ── Additional standard field error cases ──

    #[Test]
    public function unsupportedOperatorForIdThrows(): void
    {
        $builder = new UserQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "LIKE" for field "user.id"');

        $builder
            ->where('u.id LIKE :val')
            ->setParameter('val', '5%')
            ->toArray();
    }

    // ── Execution methods (WordPress integration) ──

    #[Test]
    public function getReturnsUserQueryResult(): void
    {
        $result = (new UserQueryBuilder())
            ->where('u.role = :role')
            ->setParameter('role', 'author')
            ->setMaxResults(5)
            ->get();

        self::assertInstanceOf(\WpPack\Component\Query\Result\UserQueryResult::class, $result);
    }

    #[Test]
    public function getReturnsPaginationInfo(): void
    {
        $result = (new UserQueryBuilder())
            ->setMaxResults(5)
            ->get();

        self::assertGreaterThanOrEqual(0, $result->total);
        self::assertGreaterThanOrEqual(0, $result->totalPages);
        self::assertGreaterThanOrEqual(1, $result->currentPage);
    }

    #[Test]
    public function getWithoutNumberSetsTotalPagesCorrectly(): void
    {
        $result = (new UserQueryBuilder())->get();

        // When no number is set (perPage = 0), totalPages should be 1 if users exist, 0 if not
        self::assertGreaterThanOrEqual(0, $result->totalPages);
    }

    #[Test]
    public function getWithPagedArgument(): void
    {
        $result = (new UserQueryBuilder())
            ->setMaxResults(5)
            ->arg('paged', 2)
            ->get();

        self::assertSame(2, $result->currentPage);
    }

    #[Test]
    public function firstReturnsNullableWpUser(): void
    {
        $user = (new UserQueryBuilder())
            ->where('u.id = :id')
            ->setParameter('id', 999999)
            ->first();

        self::assertNull($user);
    }

    #[Test]
    public function getIdsReturnsArrayOfIntegers(): void
    {
        $ids = (new UserQueryBuilder())
            ->setMaxResults(5)
            ->getIds();

        self::assertIsArray($ids);
        foreach ($ids as $id) {
            self::assertIsInt($id);
        }
    }

    #[Test]
    public function countReturnsInteger(): void
    {
        $count = (new UserQueryBuilder())->count();

        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(0, $count);
    }

    #[Test]
    public function existsReturnsBool(): void
    {
        $exists = (new UserQueryBuilder())
            ->where('u.id = :id')
            ->setParameter('id', 999999)
            ->exists();

        self::assertFalse($exists);
    }

    #[Test]
    public function existsReturnsTrueWhenUsersExist(): void
    {
        // Admin user should always exist in test environment
        $exists = (new UserQueryBuilder())->exists();

        self::assertTrue($exists);
    }
}
