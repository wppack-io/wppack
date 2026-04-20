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
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Filter\ComparisonNode;
use WPPack\Component\Scim\Filter\FilterNode;
use WPPack\Component\Scim\Filter\LogicalNode;

#[CoversClass(ComparisonNode::class)]
#[CoversClass(LogicalNode::class)]
final class FilterNodesTest extends TestCase
{
    #[Test]
    public function comparisonNodeCarriesAttributePathOperatorAndValue(): void
    {
        $node = new ComparisonNode('userName', 'eq', 'alice');

        self::assertInstanceOf(FilterNode::class, $node);
        self::assertSame('userName', $node->attributePath);
        self::assertSame('eq', $node->operator);
        self::assertSame('alice', $node->value);
    }

    #[Test]
    public function comparisonNodePresentOperatorAllowsNullValue(): void
    {
        $node = new ComparisonNode('emails.value', 'pr', null);

        self::assertSame('pr', $node->operator);
        self::assertNull($node->value);
    }

    #[Test]
    public function logicalNodeBranchesAreExposed(): void
    {
        $left = new ComparisonNode('userName', 'eq', 'alice');
        $right = new ComparisonNode('active', 'eq', 'true');

        $node = new LogicalNode('and', $left, $right);

        self::assertInstanceOf(FilterNode::class, $node);
        self::assertSame('and', $node->operator);
        self::assertSame($left, $node->left);
        self::assertSame($right, $node->right);
    }

    #[Test]
    public function logicalNodeSupportsOrOperator(): void
    {
        $node = new LogicalNode(
            'or',
            new ComparisonNode('a', 'eq', '1'),
            new ComparisonNode('b', 'eq', '2'),
        );

        self::assertSame('or', $node->operator);
    }

    #[Test]
    public function logicalNodesCanBeNested(): void
    {
        $inner = new LogicalNode(
            'or',
            new ComparisonNode('a', 'eq', '1'),
            new ComparisonNode('b', 'eq', '2'),
        );

        $outer = new LogicalNode('and', $inner, new ComparisonNode('active', 'eq', 'true'));

        self::assertInstanceOf(LogicalNode::class, $outer->left);
        self::assertSame('or', $outer->left->operator);
    }
}
