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

namespace WPPack\Component\Database\Bridge\MysqlDataApi;

use AsyncAws\RdsDataService\RdsDataServiceClient;
use WPPack\Component\Database\Driver\DataApiDriverTrait;
use WPPack\Component\Database\Driver\MysqlDriver;

/**
 * Aurora MySQL Data API driver.
 *
 * HTTP-based, stateless driver using RDS Data API. Extends MysqlDriver
 * for platform/translator compatibility, overrides all I/O with HTTP calls.
 *
 * DSN: mysql+dataapi://cluster-arn/dbname?secret_arn=xxx&region=us-east-1
 */
class MysqlDataApiDriver extends MysqlDriver
{
    use DataApiDriverTrait;

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

        parent::__construct(
            host: '',
            username: '',
            password: '',
            database: $database,
        );
    }

    public function getName(): string
    {
        return 'mysql+dataapi';
    }
}
