<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\SiteHealth\Status;

final class StatusTest extends TestCase
{
    #[Test]
    public function goodCaseHasCorrectValue(): void
    {
        self::assertSame('good', Status::Good->value);
    }

    #[Test]
    public function recommendedCaseHasCorrectValue(): void
    {
        self::assertSame('recommended', Status::Recommended->value);
    }

    #[Test]
    public function criticalCaseHasCorrectValue(): void
    {
        self::assertSame('critical', Status::Critical->value);
    }

    #[Test]
    public function goodBadgeColorIsGreen(): void
    {
        self::assertSame('green', Status::Good->badgeColor());
    }

    #[Test]
    public function recommendedBadgeColorIsOrange(): void
    {
        self::assertSame('orange', Status::Recommended->badgeColor());
    }

    #[Test]
    public function criticalBadgeColorIsRed(): void
    {
        self::assertSame('red', Status::Critical->badgeColor());
    }

    #[Test]
    public function canBeCreatedFromString(): void
    {
        self::assertSame(Status::Good, Status::from('good'));
        self::assertSame(Status::Recommended, Status::from('recommended'));
        self::assertSame(Status::Critical, Status::from('critical'));
    }
}
