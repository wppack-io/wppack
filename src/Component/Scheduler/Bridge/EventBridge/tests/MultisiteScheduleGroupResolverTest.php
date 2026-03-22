<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\MultisiteScheduleGroupResolver;
use WpPack\Component\Scheduler\Bridge\EventBridge\ScheduleGroupResolverInterface;

final class MultisiteScheduleGroupResolverTest extends TestCase
{
    private MultisiteScheduleGroupResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MultisiteScheduleGroupResolver();
    }

    #[Test]
    public function implementsScheduleGroupResolverInterface(): void
    {
        self::assertInstanceOf(ScheduleGroupResolverInterface::class, $this->resolver);
    }

    #[Test]
    public function mainSiteReturnsDefaultGroup(): void
    {
        self::assertSame('wppack', $this->resolver->resolve(get_main_site_id()));
    }

    #[Test]
    public function blogIdZeroReturnsDefaultGroup(): void
    {
        self::assertSame('wppack', $this->resolver->resolve(0));
    }

    #[Test]
    public function negativeIdReturnsDefaultGroup(): void
    {
        self::assertSame('wppack', $this->resolver->resolve(-1));
    }

    #[Test]
    public function subSiteReturnsSuffixedGroup(): void
    {
        self::assertSame('wppack_2', $this->resolver->resolve(2));
    }

    #[Test]
    public function largeSubSiteIdReturnsSuffixedGroup(): void
    {
        self::assertSame('wppack_999', $this->resolver->resolve(999));
    }

    #[Test]
    public function nullBlogIdFallsBackToDefault(): void
    {
        self::assertSame('wppack', $this->resolver->resolve());
    }

    #[Test]
    public function customPrefixForMainSite(): void
    {
        $resolver = new MultisiteScheduleGroupResolver(prefix: 'myapp');

        self::assertSame('myapp', $resolver->resolve(get_main_site_id()));
    }

    #[Test]
    public function customPrefixForSubSite(): void
    {
        $resolver = new MultisiteScheduleGroupResolver(prefix: 'myapp');

        self::assertSame('myapp_3', $resolver->resolve(3));
    }
}
