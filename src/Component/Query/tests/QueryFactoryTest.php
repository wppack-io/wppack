<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Builder\PostQueryBuilder;
use WpPack\Component\Query\Builder\TermQueryBuilder;
use WpPack\Component\Query\Builder\UserQueryBuilder;
use WpPack\Component\Query\QueryFactory;

final class QueryFactoryTest extends TestCase
{
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

    #[Test]
    public function usersReturnsUserQueryBuilder(): void
    {
        $factory = new QueryFactory();
        $builder = $factory->users();

        self::assertInstanceOf(UserQueryBuilder::class, $builder);
    }

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
}
