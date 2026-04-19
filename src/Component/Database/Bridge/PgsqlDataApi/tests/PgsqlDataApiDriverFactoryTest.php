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

namespace WPPack\Component\Database\Bridge\PgsqlDataApi\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\PgsqlDataApi\PgsqlDataApiDriverFactory;
use WPPack\Component\Dsn\Dsn;

final class PgsqlDataApiDriverFactoryTest extends TestCase
{
    #[Test]
    public function supportsPgsqlDataApiScheme(): void
    {
        $factory = new PgsqlDataApiDriverFactory();

        if (!class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            self::assertFalse($factory->supports(Dsn::fromString('pgsql+dataapi://arn/db')));

            return;
        }

        self::assertTrue($factory->supports(Dsn::fromString('pgsql+dataapi://arn/db')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new PgsqlDataApiDriverFactory();

        self::assertFalse($factory->supports(Dsn::fromString('pgsql://host/db')));
        self::assertFalse($factory->supports(Dsn::fromString('mysql+dataapi://arn/db')));
    }

    #[Test]
    public function definitions(): void
    {
        $defs = PgsqlDataApiDriverFactory::definitions();

        self::assertCount(1, $defs);
        self::assertSame('pgsql+dataapi', $defs[0]->scheme);
        self::assertSame('Aurora PostgreSQL Data API', $defs[0]->label);
    }

    #[Test]
    public function createsDriverFromDsn(): void
    {
        if (!class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            self::markTestSkipped('async-aws/rds-data-service not installed.');
        }

        $factory = new PgsqlDataApiDriverFactory();
        $driver = $factory->create(Dsn::fromString('pgsql+dataapi://arn:aws:rds:us-east-1:123:cluster:my-cluster/mydb?secret_arn=arn:secret'));

        self::assertSame('pgsql+dataapi', $driver->getName());
    }
}
