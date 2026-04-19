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

namespace WPPack\Component\Database\Bridge\MysqlDataApi\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\MysqlDataApi\MysqlDataApiDriverFactory;
use WPPack\Component\Dsn\Dsn;

final class MysqlDataApiDriverFactoryTest extends TestCase
{
    #[Test]
    public function supportsMysqlDataApiScheme(): void
    {
        $factory = new MysqlDataApiDriverFactory();

        if (!class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            self::assertFalse($factory->supports(Dsn::fromString('mysql+dataapi://arn/db')));

            return;
        }

        self::assertTrue($factory->supports(Dsn::fromString('mysql+dataapi://arn/db')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new MysqlDataApiDriverFactory();

        self::assertFalse($factory->supports(Dsn::fromString('mysql://host/db')));
        self::assertFalse($factory->supports(Dsn::fromString('pgsql+dataapi://arn/db')));
    }

    #[Test]
    public function definitions(): void
    {
        $defs = MysqlDataApiDriverFactory::definitions();

        self::assertCount(1, $defs);
        self::assertSame('mysql+dataapi', $defs[0]->scheme);
        self::assertSame('Aurora MySQL Data API', $defs[0]->label);
    }

    #[Test]
    public function createsDriverFromDsn(): void
    {
        if (!class_exists(\AsyncAws\RdsDataService\RdsDataServiceClient::class)) {
            self::markTestSkipped('async-aws/rds-data-service not installed.');
        }

        $factory = new MysqlDataApiDriverFactory();
        $driver = $factory->create(Dsn::fromString('mysql+dataapi://arn:aws:rds:us-east-1:123:cluster:my-cluster/mydb?secret_arn=arn:secret'));

        self::assertSame('mysql+dataapi', $driver->getName());
    }
}
