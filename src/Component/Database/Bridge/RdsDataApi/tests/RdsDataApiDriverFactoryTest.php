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

namespace WpPack\Component\Database\Bridge\RdsDataApi\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Bridge\RdsDataApi\RdsDataApiDriverFactory;
use WpPack\Component\Dsn\Dsn;

final class RdsDataApiDriverFactoryTest extends TestCase
{
    #[Test]
    public function supportsRdsDataScheme(): void
    {
        $factory = new RdsDataApiDriverFactory();

        if (!class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            self::assertFalse($factory->supports(Dsn::fromString('rds-data://arn/db')));

            return;
        }

        self::assertTrue($factory->supports(Dsn::fromString('rds-data://arn/db')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new RdsDataApiDriverFactory();

        self::assertFalse($factory->supports(Dsn::fromString('mysql://host/db')));
        self::assertFalse($factory->supports(Dsn::fromString('sqlite:///path')));
    }

    #[Test]
    public function definitions(): void
    {
        $defs = RdsDataApiDriverFactory::definitions();

        self::assertCount(1, $defs);
        self::assertSame('rds-data', $defs[0]->scheme);
        self::assertSame('RDS Data API (Aurora Serverless)', $defs[0]->label);
    }
}
