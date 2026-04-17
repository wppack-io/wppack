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

namespace WpPack\Component\Database\Bridge\PgsqlDataApi;

use AsyncAws\RdsDataService\RdsDataServiceClient;
use WpPack\Component\Database\Driver\DataApiDriverTrait;
use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WpPack\Component\Database\Bridge\Pgsql\Translator\PostgresqlQueryTranslator;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Aurora PostgreSQL Data API driver.
 *
 * HTTP-based, stateless driver using RDS Data API. Extends PgsqlDriver
 * for platform/translator compatibility, overrides all I/O with HTTP calls.
 *
 * DSN: pgsql+dataapi://cluster-arn/dbname?secret_arn=xxx&region=us-east-1
 */
class PgsqlDataApiDriver extends PgsqlDriver
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
        return 'pgsql+dataapi';
    }

    public function getQueryTranslator(): QueryTranslatorInterface
    {
        return new PostgresqlQueryTranslator($this);
    }

    protected function doLastInsertId(): int
    {
        // RDS Data API is HTTP-stateless: every call lands in its own
        // session so lastval() is almost always undefined (set in one
        // request, read in the next). Rather than propagating the
        // engine-side 'lastval is not yet defined in this session' error
        // as a DriverException, return 0 to match the standard wpdb
        // contract (insert_id == 0 means 'nothing to report').
        try {
            $result = $this->executeQuery('SELECT lastval() AS id');
            $row = $result->fetchAssociative();

            return $row !== null ? (int) ($row['id'] ?? 0) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
