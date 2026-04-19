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

namespace WPPack\Component\Site\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Site\SiteRepository;

#[CoversClass(SiteRepository::class)]
final class SiteRepositoryTest extends TestCase
{
    private SiteRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new SiteRepository();
    }

    #[Test]
    public function findAllReturnsArray(): void
    {
        $sites = $this->repository->findAll();

        self::assertIsArray($sites);
    }

    #[Test]
    public function findReturnsNullForInvalidId(): void
    {
        $site = $this->repository->find(999999);

        self::assertNull($site);
    }

    #[Test]
    public function findByUrlReturnsNullWhenNotFound(): void
    {
        $site = $this->repository->findByUrl('nonexistent.example.com');

        self::assertNull($site);
    }

    #[Test]
    public function findBySlugReturnsNullWhenNotFound(): void
    {
        $site = $this->repository->findBySlug('nonexistent-slug-' . uniqid());

        self::assertNull($site);
    }

    #[Test]
    public function getAllDomainsReturnsUniqueList(): void
    {
        $domains = $this->repository->getAllDomains();

        self::assertIsArray($domains);
        self::assertSame(array_values(array_unique($domains)), $domains);
    }

    #[Test]
    public function metaCrud(): void
    {
        if (!is_multisite()) {
            self::markTestSkipped('Site meta requires multisite.');
        }

        $blogId = get_current_blog_id();

        // addMeta
        $metaId = $this->repository->addMeta($blogId, 'test_site_key', 'test_value');
        self::assertIsInt($metaId);
        self::assertGreaterThan(0, $metaId);

        // getMeta (single)
        $value = $this->repository->getMeta($blogId, 'test_site_key', true);
        self::assertSame('test_value', $value);

        // getMeta (all for key)
        $values = $this->repository->getMeta($blogId, 'test_site_key');
        self::assertIsArray($values);
        self::assertContains('test_value', $values);

        // updateMeta
        $this->repository->updateMeta($blogId, 'test_site_key', 'updated_value');
        $value = $this->repository->getMeta($blogId, 'test_site_key', true);
        self::assertSame('updated_value', $value);

        // deleteMeta
        $deleted = $this->repository->deleteMeta($blogId, 'test_site_key');
        self::assertTrue($deleted);

        $value = $this->repository->getMeta($blogId, 'test_site_key', true);
        self::assertSame('', $value);
    }
}
