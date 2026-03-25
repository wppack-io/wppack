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

namespace WpPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\Exception\MissingParametersException;
use WpPack\Component\Rest\Exception\RouteNotFoundException;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Rest\RestUrlGenerator;

#[CoversClass(RestUrlGenerator::class)]
final class RestUrlGeneratorTest extends TestCase
{
    #[Test]
    public function urlReturnsRestUrlWithPath(): void
    {
        $registry = new RestRegistry(new Request());
        $generator = new RestUrlGenerator($registry);

        $url = $generator->url('wppack/v1/test');

        self::assertStringContainsString('wppack/v1/test', $url);
    }

    #[Test]
    public function urlReturnsRestUrlWithoutPath(): void
    {
        $registry = new RestRegistry(new Request());
        $generator = new RestUrlGenerator($registry);

        $url = $generator->url();

        self::assertNotEmpty($url);
        self::assertSame(rest_url(), $url);
    }

    #[Test]
    public function prefixReturnsRestUrlPrefix(): void
    {
        $registry = new RestRegistry(new Request());
        $generator = new RestUrlGenerator($registry);

        $prefix = $generator->prefix();

        self::assertSame('wp-json', $prefix);
    }

    #[Test]
    public function generateWithSingleParam(): void
    {
        $registry = new RestRegistry(new Request());
        $registry->register(new #[RestRoute('/products/{id}', namespace: 'test/v1', methods: [HttpMethod::GET], name: 'product_show')] #[Permission(public: true)] class {
            public function __invoke(int $id): array
            {
                return [];
            }
        });

        $generator = new RestUrlGenerator($registry);
        $url = $generator->generate('product_show', ['id' => '42']);

        self::assertStringContainsString('test/v1/products/42', $url);
    }

    #[Test]
    public function generateWithMultipleParams(): void
    {
        $registry = new RestRegistry(new Request());
        $registry->register(new #[RestRoute('/events', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute('/{year}/{month}', methods: [HttpMethod::GET], name: 'event_archive')]
            public function archive(string $year, string $month): array
            {
                return [];
            }
        });

        $generator = new RestUrlGenerator($registry);
        $url = $generator->generate('event_archive', ['year' => '2024', 'month' => '03']);

        self::assertStringContainsString('test/v1/events/2024/03', $url);
    }

    #[Test]
    public function generateWithNoParams(): void
    {
        $registry = new RestRegistry(new Request());
        $registry->register(new #[RestRoute('/items', namespace: 'test/v1', methods: [HttpMethod::GET], name: 'item_list')] #[Permission(public: true)] class {
            public function __invoke(): array
            {
                return [];
            }
        });

        $generator = new RestUrlGenerator($registry);
        $url = $generator->generate('item_list');

        self::assertStringContainsString('test/v1/items', $url);
    }

    #[Test]
    public function generateThrowsForNonexistentRoute(): void
    {
        $registry = new RestRegistry(new Request());
        $generator = new RestUrlGenerator($registry);

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route "nonexistent" does not exist.');

        $generator->generate('nonexistent');
    }

    #[Test]
    public function generateThrowsForMissingParams(): void
    {
        $registry = new RestRegistry(new Request());
        $registry->register(new #[RestRoute('/products/{id}', namespace: 'test/v1', methods: [HttpMethod::GET], name: 'product_missing')] #[Permission(public: true)] class {
            public function __invoke(int $id): array
            {
                return [];
            }
        });

        $generator = new RestUrlGenerator($registry);

        $this->expectException(MissingParametersException::class);
        $this->expectExceptionMessage('Missing parameters "id" for route "product_missing".');

        $generator->generate('product_missing');
    }
}
