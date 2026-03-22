<?php

declare(strict_types=1);

namespace WpPack\Component\Site\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Site\SiteRepository;

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
    public function findByIdReturnsNullForInvalidId(): void
    {
        $site = $this->repository->findById(999999);

        self::assertNull($site);
    }

    #[Test]
    public function findByUrlReturnsNullWhenNotFound(): void
    {
        $id = $this->repository->findByUrl('nonexistent.example.com');

        self::assertNull($id);
    }

    #[Test]
    public function findBySlugReturnsNullWhenNotFound(): void
    {
        $id = $this->repository->findBySlug('nonexistent-slug-' . uniqid());

        self::assertNull($id);
    }

    #[Test]
    public function getAllDomainsReturnsUniqueList(): void
    {
        $domains = $this->repository->getAllDomains();

        self::assertIsArray($domains);
        self::assertSame(array_values(array_unique($domains)), $domains);
    }
}
