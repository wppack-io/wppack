<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\Exception\MissingParametersException;
use WpPack\Component\Routing\Exception\RouteNotFoundException;
use WpPack\Component\Routing\Generator\UrlGenerator;
use WpPack\Component\Routing\Response\TemplateResponse;
use WpPack\Component\Routing\RouteRegistry;

#[CoversClass(UrlGenerator::class)]
#[CoversClass(RouteNotFoundException::class)]
final class UrlGeneratorTest extends TestCase
{
    #[Test]
    public function generateWithSingleParam(): void
    {
        $registry = new RouteRegistry();
        $registry->register(new #[Route('/products/{product_slug}', name: 'product_detail')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        });

        $generator = new UrlGenerator($registry);

        self::assertSame('/products/foo', $generator->generate('product_detail', ['product_slug' => 'foo']));
    }

    #[Test]
    public function generateWithMultipleParams(): void
    {
        $registry = new RouteRegistry();
        $registry->register(new class {
            #[Route('/events/{year}/{month}', name: 'event_archive')]
            public function archive(): ?TemplateResponse
            {
                return null;
            }
        });

        $generator = new UrlGenerator($registry);

        self::assertSame('/events/2024/03', $generator->generate('event_archive', ['year' => '2024', 'month' => '03']));
    }

    #[Test]
    public function generateWithIntParams(): void
    {
        $registry = new RouteRegistry();
        $registry->register(new class {
            #[Route('/events/{year}/{month}', name: 'event_archive_int')]
            public function archive(): ?TemplateResponse
            {
                return null;
            }
        });

        $generator = new UrlGenerator($registry);

        self::assertSame('/events/2024/3', $generator->generate('event_archive_int', ['year' => 2024, 'month' => 3]));
    }

    #[Test]
    public function generateThrowsForNonexistentRoute(): void
    {
        $registry = new RouteRegistry();

        $generator = new UrlGenerator($registry);

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route "nonexistent" does not exist.');

        $generator->generate('nonexistent');
    }

    #[Test]
    public function generateWithNoParams(): void
    {
        $registry = new RouteRegistry();
        $registry->register(new #[Route('/static-page', name: 'static_page')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        });

        $generator = new UrlGenerator($registry);

        self::assertSame('/static-page', $generator->generate('static_page'));
    }

    #[Test]
    public function generateThrowsForMissingParams(): void
    {
        $registry = new RouteRegistry();
        $registry->register(new #[Route('/products/{product_slug}', name: 'product_missing')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        });

        $generator = new UrlGenerator($registry);

        $this->expectException(MissingParametersException::class);
        $this->expectExceptionMessage('Missing parameters "product_slug" for route "product_missing".');

        $generator->generate('product_missing');
    }

    #[Test]
    public function generateThrowsForPartiallyMissingParams(): void
    {
        $registry = new RouteRegistry();
        $registry->register(new class {
            #[Route('/events/{year}/{month}', name: 'event_partial')]
            public function archive(): ?TemplateResponse
            {
                return null;
            }
        });

        $generator = new UrlGenerator($registry);

        $this->expectException(MissingParametersException::class);
        $this->expectExceptionMessage('Missing parameters "month" for route "event_partial".');

        $generator->generate('event_partial', ['year' => '2024']);
    }

    #[Test]
    public function generateNormalizesLeadingSlash(): void
    {
        $registry = new RouteRegistry();
        $registry->register(new #[Route('no-leading-slash/{id}', name: 'no_slash')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        });

        $generator = new UrlGenerator($registry);

        self::assertSame('/no-leading-slash/42', $generator->generate('no_slash', ['id' => '42']));
    }
}
