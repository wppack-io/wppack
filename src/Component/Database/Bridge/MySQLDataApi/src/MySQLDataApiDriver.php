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

namespace WPPack\Component\Database\Bridge\MySQLDataApi;

use AsyncAws\RdsDataService\RdsDataServiceClient;
use WPPack\Component\Database\Driver\AbstractDriver;
use WPPack\Component\Database\Driver\DataApiDriverTrait;
use WPPack\Component\Database\Platform\MySQLPlatform;
use WPPack\Component\Database\Platform\PlatformInterface;

/**
 * Aurora MySQL Data API driver.
 *
 * HTTP-based, stateless driver that talks to RDS Data API. It reuses
 * MySQLPlatform for SQL dialect parity with the native MySQL driver but
 * does NOT extend MySQLDriver — there is no mysqli handle here, only an
 * RdsDataServiceClient, so getNativeConnection() returns the HTTP client
 * rather than any legacy database resource.
 *
 * DSN: mysql+dataapi://cluster-arn/dbname?secret_arn=xxx&region=us-east-1
 */
class MySQLDataApiDriver extends AbstractDriver
{
    use DataApiDriverTrait;

    private ?PlatformInterface $platform = null;

    public function __construct(
        RdsDataServiceClient $client,
        string $resourceArn,
        #[\SensitiveParameter]
        string $secretArn,
        string $database,
    ) {
        $this->dataApiClient = $client;
        $this->resourceArn = $resourceArn;
        $this->secretArn = $secretArn;
        $this->dataApiDatabase = $database;
    }

    public function getName(): string
    {
        return 'mysql+dataapi';
    }

    public function getPlatform(): PlatformInterface
    {
        return $this->platform ??= new MySQLPlatform();
    }
}
