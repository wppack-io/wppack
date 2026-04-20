<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Scim\Tests\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Exception\InvalidFilterException;
use WPPack\Component\Scim\Filter\ComparisonNode;
use WPPack\Component\Scim\Filter\FilterParser;
use WPPack\Component\Scim\Filter\LogicalNode;
use WPPack\Component\Scim\Filter\WpUserQueryAdapter;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\User\UserRepositoryInterface;

#[CoversClass(WpUserQueryAdapter::class)]
final class WpUserQueryAdapterTest extends TestCase
{
    private FilterParser $parser;

    /** @var MockObject&UserRepositoryInterface */
    private MockObject $users;
    private WpUserQueryAdapter $adapter;

    protected function setUp(): void
    {
        $this->parser = new FilterParser();
        $this->users = $this->createMock(UserRepositoryInterface::class);
        $this->adapter = new WpUserQueryAdapter($this->users);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArgs(string $filter): array
    {
        return $this->adapter->toQueryArgs($this->parser->parse($filter));
    }

    // ── Simple attribute mapping ────────────────────────────────────

    #[Test]
    public function userNameEqUsesLoginField(): void
    {
        self::assertSame(['login' => 'alice'], $this->toArgs('userName eq "alice"'));
    }

    #[Test]
    public function emailEqUsesUserEmailSearchColumn(): void
    {
        self::assertSame(
            ['search' => 'alice@example.com', 'search_columns' => ['user_email']],
            $this->toArgs('emails.value eq "alice@example.com"'),
        );
    }

    #[Test]
    public function displayNameEqUsesDisplayNameColumn(): void
    {
        self::assertSame(
            ['search' => 'Alice', 'search_columns' => ['display_name']],
            $this->toArgs('displayName eq "Alice"'),
        );
    }

    #[Test]
    public function coOperatorWrapsValueInWildcards(): void
    {
        self::assertSame(
            ['search' => '*ali*', 'search_columns' => ['user_login']],
            $this->toArgs('userName co "ali"'),
        );
    }

    #[Test]
    public function swOperatorAnchorsAtStart(): void
    {
        self::assertSame(
            ['search' => 'ali*', 'search_columns' => ['user_login']],
            $this->toArgs('userName sw "ali"'),
        );
    }

    #[Test]
    public function ewOperatorAnchorsAtEnd(): void
    {
        self::assertSame(
            ['search' => '*ali', 'search_columns' => ['user_login']],
            $this->toArgs('userName ew "ali"'),
        );
    }

    #[Test]
    public function prOperatorEmitsNoFilter(): void
    {
        self::assertSame([], $this->toArgs('userName pr'));
    }

    #[Test]
    public function neOperatorExcludesMatchingUserIds(): void
    {
        $alice = new \WP_User();
        $alice->ID = 42;
        $bob = new \WP_User();
        $bob->ID = 99;

        $this->users->expects(self::once())
            ->method('findAll')
            ->with(['login' => 'alice'])
            ->willReturn([$alice, $bob]);

        self::assertSame(['exclude' => [42, 99]], $this->toArgs('userName ne "alice"'));
    }

    // ── Meta-backed attributes ──────────────────────────────────────

    #[Test]
    public function externalIdEqBuildsMetaQuery(): void
    {
        self::assertSame([
            'meta_query' => [
                ['key' => ScimConstants::META_EXTERNAL_ID, 'value' => 'ext-1', 'compare' => '='],
            ],
        ], $this->toArgs('externalId eq "ext-1"'));
    }

    #[Test]
    public function activeEqTrueMapsToOne(): void
    {
        $args = $this->toArgs('active eq "true"');

        self::assertSame('1', $args['meta_query'][0]['value']);
        self::assertSame(ScimConstants::META_ACTIVE, $args['meta_query'][0]['key']);
    }

    #[Test]
    public function activeEqFalseMapsToZero(): void
    {
        $args = $this->toArgs('active eq "false"');

        self::assertSame('0', $args['meta_query'][0]['value']);
    }

    #[Test]
    public function metaCoEmitsLikeCompare(): void
    {
        $args = $this->toArgs('externalId co "ext"');

        self::assertSame('LIKE', $args['meta_query'][0]['compare']);
    }

    #[Test]
    public function metaSwEmitsAnchoredRegex(): void
    {
        $args = $this->toArgs('externalId sw "ext-1"');

        self::assertSame('REGEXP', $args['meta_query'][0]['compare']);
        self::assertStringStartsWith('^', $args['meta_query'][0]['value']);
    }

    #[Test]
    public function metaEwEmitsAnchoredRegex(): void
    {
        $args = $this->toArgs('externalId ew "-1"');

        self::assertSame('REGEXP', $args['meta_query'][0]['compare']);
        self::assertStringEndsWith('$', $args['meta_query'][0]['value']);
    }

    #[Test]
    public function metaNeEmitsNotEquals(): void
    {
        $args = $this->toArgs('externalId ne "ext-1"');

        self::assertSame('!=', $args['meta_query'][0]['compare']);
    }

    #[Test]
    public function metaPrEmitsExistsCompareWithoutValue(): void
    {
        $args = $this->toArgs('externalId pr');

        self::assertSame('EXISTS', $args['meta_query'][0]['compare']);
        self::assertArrayNotHasKey('value', $args['meta_query'][0]);
    }

    #[Test]
    public function nameGivenNameRoutesThroughMetaPath(): void
    {
        $args = $this->toArgs('name.givenName eq "Alice"');

        // The adapter treats nested "name.*" paths as meta-backed to avoid
        // user_meta keys colliding with WP core search columns.
        self::assertArrayHasKey('meta_query', $args);
        self::assertSame('Alice', $args['meta_query'][0]['value']);
    }

    // ── AND / OR combinators ────────────────────────────────────────

    #[Test]
    public function andCombinesTwoMetaConditionsWithAndRelation(): void
    {
        $args = $this->toArgs('externalId eq "e1" and active eq "true"');

        self::assertSame('AND', $args['meta_query']['relation']);
        self::assertSame('e1', $args['meta_query'][0][0]['value']);
        self::assertSame('1', $args['meta_query'][1][0]['value']);
    }

    #[Test]
    public function orCombinesTwoMetaConditionsWithOrRelation(): void
    {
        $args = $this->toArgs('externalId eq "e1" or externalId eq "e2"');

        self::assertSame('OR', $args['meta_query']['relation']);
    }

    #[Test]
    public function andWithMetaAndTopLevelFieldMergesBoth(): void
    {
        // meta_query lives on its own, search/search_columns stay alongside.
        $args = $this->toArgs('userName eq "alice" and externalId eq "e1"');

        self::assertSame('alice', $args['login']);
        self::assertSame('AND', $args['meta_query']['relation']);
    }

    #[Test]
    public function orMixingTwoTopLevelFieldsIsRejected(): void
    {
        // WP_User_Query does not let us OR across search/login/etc at the
        // top level — surface the limitation instead of silently dropping
        // one side via array_merge.
        $this->expectException(InvalidFilterException::class);
        $this->toArgs('userName eq "alice" or displayName eq "Alice"');
    }

    #[Test]
    public function andMixingTwoSearchAttributesIsRejected(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->toArgs('displayName eq "A" and emails.value eq "a@example.com"');
    }

    // ── Error paths ─────────────────────────────────────────────────

    #[Test]
    public function unsupportedAttributeThrows(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->toArgs('unknownAttr eq "x"');
    }

    #[Test]
    public function unsupportedComparisonOperatorThrows(): void
    {
        // gt is valid for parsing but unsupported for userName column
        $this->expectException(InvalidFilterException::class);
        $this->toArgs('userName gt "a"');
    }

    #[Test]
    public function unsupportedOperatorOnMetaAttributeThrows(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->toArgs('externalId gt "x"');
    }

    #[Test]
    public function unknownFilterNodeTypeThrows(): void
    {
        $weird = new class implements \WPPack\Component\Scim\Filter\FilterNode {};

        $this->expectException(InvalidFilterException::class);
        $this->adapter->toQueryArgs($weird);
    }

    // ── Make sure the LogicalNode walker recurses ──────────────────

    #[Test]
    public function nestedLogicalTreeIsHandled(): void
    {
        // (a and b) or c — both sides must emit meta_query for OR to work.
        $tree = new LogicalNode(
            'or',
            new LogicalNode(
                'and',
                new ComparisonNode('externalId', 'eq', 'a'),
                new ComparisonNode('active', 'eq', 'true'),
            ),
            new ComparisonNode('externalId', 'eq', 'c'),
        );

        $args = $this->adapter->toQueryArgs($tree);

        self::assertSame('OR', $args['meta_query']['relation']);
    }
}
