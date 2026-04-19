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

namespace WPPack\Component\Database\Driver;

use AsyncAws\RdsDataService\Input\BeginTransactionRequest;
use AsyncAws\RdsDataService\Input\CommitTransactionRequest;
use AsyncAws\RdsDataService\Input\ExecuteStatementRequest;
use AsyncAws\RdsDataService\Input\RollbackTransactionRequest;
use AsyncAws\RdsDataService\RdsDataServiceClient;
use AsyncAws\RdsDataService\ValueObject\Field;
use AsyncAws\RdsDataService\ValueObject\FieldMemberBlobValue;
use AsyncAws\RdsDataService\ValueObject\FieldMemberBooleanValue;
use AsyncAws\RdsDataService\ValueObject\FieldMemberDoubleValue;
use AsyncAws\RdsDataService\ValueObject\FieldMemberIsNull;
use AsyncAws\RdsDataService\ValueObject\FieldMemberLongValue;
use AsyncAws\RdsDataService\ValueObject\FieldMemberStringValue;
use AsyncAws\RdsDataService\ValueObject\SqlParameter;
use WPPack\Component\Database\Exception\CredentialsExpiredException;
use WPPack\Component\Database\Exception\DriverException;
use WPPack\Component\Database\Exception\DriverThrottledException;
use WPPack\Component\Database\Exception\DriverTimeoutException;
use WPPack\Component\Database\Result;
use WPPack\Component\Database\Statement;

/**
 * Shared RDS Data API logic for MySQL and PostgreSQL Data API drivers.
 *
 * HTTP-based, stateless — no persistent connections.
 *
 * Response-size limit: the Data API caps each ExecuteStatement response
 * at ~1 MB (subject to change by AWS). There is no nextPageToken / cursor,
 * so result sets larger than that are rejected server-side. Callers that
 * need to stream large result sets must paginate at the SQL level with
 * LIMIT / OFFSET (or keyset pagination). Result-set handling here loads
 * every row into memory; for production workloads above a few thousand
 * rows per call, use server-side pagination or the native engine driver
 * instead. doExecuteQuery emits a logger warning when the record count
 * crosses a soft threshold so operators can catch these callsites early.
 */
trait DataApiDriverTrait
{
    /**
     * Soft threshold for a Data API query response. Above this row count we
     * emit a PSR logger warning so callers can add LIMIT / keyset pagination
     * before the 1 MB hard limit starts truncating real traffic.
     */
    private const LARGE_RESULT_WARNING_THRESHOLD = 5000;
    private RdsDataServiceClient $dataApiClient;
    private string $resourceArn;
    private string $secretArn;
    private string $dataApiDatabase;
    private ?string $transactionId = null;

    /**
     * RDS Data API is stateless HTTP — there is no persistent socket to
     * check. A `true` here therefore reports only "this driver is configured
     * to talk to Data API"; it does NOT validate credential freshness,
     * network reachability, or cluster availability. Callers that treat
     * this as a liveness probe will skip legitimate reconnection / token
     * refresh work and fail opaquely when the next executeStatement hits
     * AWS. For a real health check, issue `SELECT 1` and handle any
     * DriverException that comes back.
     */
    public function isConnected(): bool
    {
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transactionId !== null;
    }

    /**
     * HTTP-based driver — no native PDO/mysqli/pgsql handle. Returning
     * mixed instead of RdsDataServiceClient keeps the trait compatible
     * with both MySQLDriver::getNativeConnection(): \mysqli and
     * PostgreSQLDriver::getNativeConnection(): \PgSql\Connection when
     * the Data API drivers subclass them for platform/translator reuse.
     */
    public function getNativeConnection(): mixed
    {
        return $this->dataApiClient;
    }

    /**
     * Display-only string-literal escape for Data API drivers.
     *
     * IMPORTANT: the output is intended for log / debug interpolation
     * only — it must NEVER be spliced into SQL sent to the server. All
     * real values leave this driver via structured parameter binding
     * (executeQuery($sql, $params)) which sends typed fields over HTTP,
     * bypassing SQL-string escaping entirely. addslashes() here
     * produces output that looks close enough to MySQL's escape shape
     * for interpolated SAVEQUERIES logs, but the result is not safe to
     * execute against PostgreSQL / DSQL where the C-string backslash
     * convention doesn't apply.
     */
    public function quoteStringLiteral(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }

    protected function doConnect(): void
    {
        // Stateless HTTP — no connection to establish
    }

    protected function doClose(): void
    {
        $this->transactionId = null;
    }

    protected function doExecuteQuery(string $sql, array $params = []): Result
    {
        $request = $this->buildDataApiRequest($sql, $params);
        $request['includeResultMetadata'] = true;

        try {
            $response = $this->dataApiClient->executeStatement(new ExecuteStatementRequest($request));
        } catch (\Throwable $e) {
            // Classify the failure before surfacing it so callers can make
            // retry decisions: throttling / timeouts are typically safe to
            // retry, credential expiry needs a token refresh first, anything
            // else is a data-level error the caller should propagate.
            throw $this->classifyDataApiError($sql, $e);
        }

        $columnMetadata = $response->getColumnMetadata();
        $records = $response->getRecords();

        $columnNames = [];

        foreach ($columnMetadata as $col) {
            $columnNames[] = $col->getLabel() ?? $col->getName() ?? '';
        }

        $rows = [];

        foreach ($records as $record) {
            $row = [];

            foreach ($record as $i => $field) {
                $name = $columnNames[$i] ?? "col_{$i}";
                $row[$name] = $this->fieldToPhp($field);
            }

            $rows[] = $row;
        }

        // The trait is consumed by MySQLDataApiDriver (MySQLDriver
        // inherits $logger) and PostgreSQLDataApiDriver (PostgreSQLDriver
        // has no logger). isset() covers both — declared-but-null and
        // undeclared both evaluate false without triggering E_WARNING,
        // whereas a plain property read on the PG side would tip PHP
        // into dynamic-property territory.
        if (\count($rows) >= self::LARGE_RESULT_WARNING_THRESHOLD
            && isset($this->logger)) {
            $this->logger->warning('RDS Data API query returned a large result set; add LIMIT / keyset pagination before hitting the 1 MB response cap', [
                'sql' => $sql,
                'rows' => \count($rows),
                'threshold' => self::LARGE_RESULT_WARNING_THRESHOLD,
            ]);
        }

        return new Result($rows, (int) $response->getNumberOfRecordsUpdated());
    }

    protected function doExecuteStatement(string $sql, array $params = []): int
    {
        $request = $this->buildDataApiRequest($sql, $params);

        try {
            $response = $this->dataApiClient->executeStatement(new ExecuteStatementRequest($request));
        } catch (\Throwable $e) {
            throw $this->classifyDataApiError($sql, $e);
        }

        return (int) $response->getNumberOfRecordsUpdated();
    }

    /**
     * Wrap an AWS-side exception in a WPPack driver exception, choosing a
     * subtype the caller can act on:
     *
     *   - DriverThrottledException: service returned 429 / ThrottlingException
     *     / similar. Safe-to-retry with exponential backoff.
     *   - DriverTimeoutException: request took longer than the service's
     *     timeout window. Safe-to-retry when the original query was
     *     idempotent.
     *   - CredentialsExpiredException: auth token / secret was rejected.
     *     Refresh the credential source and retry.
     *   - DriverException: everything else (syntax errors, schema issues,
     *     the 1 MB response cap). Caller should surface to the user.
     */
    private function classifyDataApiError(string $sql, \Throwable $e): DriverException
    {
        $maxLen = 200;
        $truncated = mb_strlen($sql) > $maxLen ? mb_substr($sql, 0, $maxLen) . '...' : $sql;
        $awsMessage = $e->getMessage();
        $awsClass = $e::class;

        $messageSuffix = \sprintf(' [SQL: %s]', $truncated);

        // Throttling / rate limit
        if (
            stripos($awsClass, 'Throttl') !== false
            || stripos($awsMessage, 'Rate exceeded') !== false
            || stripos($awsMessage, 'Throttling') !== false
            || stripos($awsMessage, ' 429 ') !== false
        ) {
            return new DriverThrottledException(
                'RDS Data API throttled: ' . $awsMessage . $messageSuffix,
                0,
                $e,
            );
        }

        // Timeouts
        if (
            stripos($awsClass, 'Timeout') !== false
            || stripos($awsMessage, 'timed out') !== false
            || stripos($awsMessage, ' 504 ') !== false
        ) {
            return new DriverTimeoutException(
                'RDS Data API request timed out: ' . $awsMessage . $messageSuffix,
                0,
                $e,
            );
        }

        // Expired / invalid credentials
        if (
            stripos($awsClass, 'ExpiredToken') !== false
            || stripos($awsClass, 'InvalidSignature') !== false
            || stripos($awsClass, 'BadRequestException') !== false && stripos($awsMessage, 'credential') !== false
            || stripos($awsMessage, 'ExpiredToken') !== false
            || stripos($awsMessage, 'signature has expired') !== false
            || stripos($awsMessage, 'secret has been rotated') !== false
        ) {
            return new CredentialsExpiredException(
                'RDS Data API credentials rejected: ' . $awsMessage . $messageSuffix,
                0,
                $e,
            );
        }

        return new DriverException(
            \sprintf(
                'RDS Data API call failed: %s (responses are capped at ~1 MB — use LIMIT / keyset pagination for large SELECTs)%s',
                $awsMessage,
                $messageSuffix,
            ),
            0,
            $e,
        );
    }

    protected function doPrepare(string $sql): Statement
    {
        $driver = $this;

        $executeQuery = static function (array $params) use ($driver, $sql): Result {
            return $driver->executeQuery($sql, $params);
        };

        $executeStatement = static function (array $params) use ($driver, $sql): int {
            return $driver->executeStatement($sql, $params);
        };

        $close = static function (): void {};

        return new Statement($executeQuery, $executeStatement, $close);
    }

    protected function doLastInsertId(): int
    {
        $result = $this->executeQuery('SELECT LAST_INSERT_ID() AS id');
        $row = $result->fetchAssociative();

        return $row !== null ? (int) ($row['id'] ?? 0) : 0;
    }

    protected function doBeginTransaction(): void
    {
        $response = $this->dataApiClient->beginTransaction(new BeginTransactionRequest([
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'database' => $this->dataApiDatabase,
        ]));

        $this->transactionId = $response->getTransactionId();
    }

    protected function doCommit(): void
    {
        if ($this->transactionId === null) {
            throw new DriverException('No active transaction to commit.');
        }

        $this->dataApiClient->commitTransaction(new CommitTransactionRequest([
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'transactionId' => $this->transactionId,
        ]));

        $this->transactionId = null;
    }

    protected function doRollBack(): void
    {
        if ($this->transactionId === null) {
            throw new DriverException('No active transaction to roll back.');
        }

        $this->dataApiClient->rollbackTransaction(new RollbackTransactionRequest([
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'transactionId' => $this->transactionId,
        ]));

        $this->transactionId = null;
    }

    /**
     * @param list<mixed> $params
     *
     * @return array<string, mixed>
     */
    private function buildDataApiRequest(string $sql, array $params): array
    {
        $request = [
            'resourceArn' => $this->resourceArn,
            'secretArn' => $this->secretArn,
            'database' => $this->dataApiDatabase,
            'sql' => $this->convertDataApiPlaceholders($sql),
        ];

        if ($this->transactionId !== null) {
            $request['transactionId'] = $this->transactionId;
        }

        if ($params !== []) {
            $request['parameters'] = $this->buildDataApiParameters($params);
        }

        return $request;
    }

    private function convertDataApiPlaceholders(string $sql): string
    {
        $index = 0;

        return (string) preg_replace_callback('/\?/', static function () use (&$index): string {
            return ':param' . (++$index);
        }, $sql);
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<SqlParameter>
     */
    private function buildDataApiParameters(array $params): array
    {
        $sqlParams = [];

        foreach ($params as $i => $value) {
            $name = 'param' . ($i + 1);

            $field = match (true) {
                $value === null => ['isNull' => true],
                \is_int($value) => ['longValue' => $value],
                \is_float($value) => ['doubleValue' => $value],
                \is_bool($value) => ['booleanValue' => $value],
                default => ['stringValue' => (string) $value],
            };

            $sqlParams[] = new SqlParameter([
                'name' => $name,
                'value' => Field::create($field),
            ]);
        }

        return $sqlParams;
    }

    /**
     * async-aws/rds-data-service ^3 made Field an abstract union — one
     * concrete subclass per value kind (FieldMemberLongValue, …,
     * FieldMemberIsNull) — so dispatch on the instance instead of the
     * legacy getLongValue()/getStringValue() accessors that ^2 exposed.
     */
    private function fieldToPhp(Field $field): mixed
    {
        return match (true) {
            $field instanceof FieldMemberIsNull => null,
            $field instanceof FieldMemberLongValue => $field->getLongValue(),
            $field instanceof FieldMemberDoubleValue => $field->getDoubleValue(),
            $field instanceof FieldMemberBooleanValue => $field->getBooleanValue(),
            $field instanceof FieldMemberBlobValue => $field->getBlobValue(),
            $field instanceof FieldMemberStringValue => $field->getStringValue(),
            default => null,
        };
    }
}
