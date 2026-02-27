<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\SiteHealth\Result;
use WpPack\Component\SiteHealth\Status;

final class ResultTest extends TestCase
{
    #[Test]
    public function goodFactoryCreatesGoodResult(): void
    {
        $result = Result::good('All good', 'Everything is fine.');

        self::assertSame(Status::Good, $result->getStatus());
        self::assertSame('All good', $result->getLabel());
        self::assertSame('Everything is fine.', $result->getDescription());
        self::assertSame('', $result->getActions());
    }

    #[Test]
    public function recommendedFactoryCreatesRecommendedResult(): void
    {
        $result = Result::recommended('Update available', 'A new version is available.', '<a href="#">Update</a>');

        self::assertSame(Status::Recommended, $result->getStatus());
        self::assertSame('Update available', $result->getLabel());
        self::assertSame('A new version is available.', $result->getDescription());
        self::assertSame('<a href="#">Update</a>', $result->getActions());
    }

    #[Test]
    public function criticalFactoryCreatesCriticalResult(): void
    {
        $result = Result::critical('Security issue', 'Immediate action required.');

        self::assertSame(Status::Critical, $result->getStatus());
        self::assertSame('Security issue', $result->getLabel());
        self::assertSame('Immediate action required.', $result->getDescription());
        self::assertSame('', $result->getActions());
    }

    #[Test]
    public function toArrayReturnsWordPressCompatibleFormat(): void
    {
        $result = Result::recommended('Update PHP', 'Your PHP version is outdated.', '<a href="#">Learn more</a>');

        $array = $result->toArray('php_version_check', 'Performance');

        self::assertSame([
            'label' => 'Update PHP',
            'status' => 'recommended',
            'badge' => [
                'label' => 'Performance',
                'color' => 'orange',
            ],
            'description' => 'Your PHP version is outdated.',
            'actions' => '<a href="#">Learn more</a>',
            'test' => 'php_version_check',
        ], $array);
    }

    #[Test]
    public function toArrayWithGoodStatus(): void
    {
        $result = Result::good('PHP is up to date', 'You are running the latest PHP version.');

        $array = $result->toArray('php_version', 'Security');

        self::assertSame('good', $array['status']);
        self::assertSame('green', $array['badge']['color']);
        self::assertSame('Security', $array['badge']['label']);
        self::assertSame('php_version', $array['test']);
    }

    #[Test]
    public function toArrayWithCriticalStatus(): void
    {
        $result = Result::critical('SSL not configured', 'Your site does not use HTTPS.');

        $array = $result->toArray('ssl_check', 'Security');

        self::assertSame('critical', $array['status']);
        self::assertSame('red', $array['badge']['color']);
    }
}
