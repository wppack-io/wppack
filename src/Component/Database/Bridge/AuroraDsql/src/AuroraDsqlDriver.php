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

namespace WpPack\Component\Database\Bridge\AuroraDsql;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ChainProvider;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Request;
use AsyncAws\Core\RequestContext;
use AsyncAws\Core\Signer\SignerV4;
use AsyncAws\Core\Stream\StringStream;
use Psr\Log\LoggerInterface;
use WpPack\Component\Database\Bridge\AuroraDsql\Translator\AuroraDsqlQueryTranslator;
use WpPack\Component\Database\Bridge\Pgsql\PgsqlDriver;
use WpPack\Component\Database\Exception\ConnectionException;
use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * Aurora DSQL driver — extends PgsqlDriver with IAM token auth, SSL, and OCC retry.
 *
 * Features (aligned with awslabs/aurora-dsql-php-pdo-pgsql):
 * - SigV4 presigned IAM tokens (auto-generated via async-aws/core)
 * - SSL verify-full + sslnegotiation=direct (libpq 17+)
 * - OCC retry with exponential backoff + jitter (single statements only)
 * - transaction() for retrying entire transaction blocks on OCC conflict
 * - Automatic token refresh before expiry
 */
class AuroraDsqlDriver extends PgsqlDriver
{
    private const OCC_INITIAL_WAIT_MS = 100;
    private const OCC_MAX_WAIT_MS = 5000;
    private const OCC_MULTIPLIER = 2.0;
    private const TOKEN_REFRESH_MARGIN_SECS = 60;

    private ?\DateTimeImmutable $tokenExpiresAt = null;
    private ?LoggerInterface $logger;
    private string $currentToken;
    private readonly string $region;
    private readonly int $tokenDurationSecs;
    private readonly int $occMaxRetries;
    private readonly ?CredentialProvider $credentialProvider;
    private readonly bool $hasStaticToken;

    public function __construct(
        string $endpoint,
        string $region,
        string $database,
        string $username = 'admin',
        #[\SensitiveParameter]
        ?string $token = null,
        int $tokenDurationSecs = 900,
        int $occMaxRetries = 3,
        ?CredentialProvider $credentialProvider = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->region = $region;
        $this->tokenDurationSecs = $tokenDurationSecs;
        $this->occMaxRetries = $occMaxRetries;
        $this->credentialProvider = $credentialProvider;
        $this->logger = $logger;
        $this->hasStaticToken = $token !== null;

        $this->currentToken = $token ?? $this->generateToken($endpoint, $region, $username, $tokenDurationSecs, $credentialProvider);

        parent::__construct(
            host: $endpoint,
            username: $username,
            password: $this->currentToken,
            database: $database,
            port: 5432,
        );
    }

    public function getPlatform(): \WpPack\Component\Database\Platform\PlatformInterface
    {
        return new DsqlPlatform();
    }

    /**
     * Override doConnect to refresh token on reconnection if needed.
     */
    protected function doConnect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        // Refresh token if auto-generated and approaching expiry
        if (!$this->hasStaticToken && $this->tokenExpiresAt !== null
            && new \DateTimeImmutable() >= $this->tokenExpiresAt) {
            $this->currentToken = $this->generateToken(
                $this->host,
                $this->region,
                $this->username,
                $this->tokenDurationSecs,
                $this->credentialProvider,
            );
        }

        $esc = static fn(string $v): string => "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $v) . "'";

        $connStr = \sprintf(
            'host=%s port=%d dbname=%s user=%s password=%s client_encoding=%s',
            $esc($this->host),
            $this->port,
            $esc($this->database),
            $esc($this->username),
            $esc($this->currentToken),
            $esc('UTF8'),
        );

        $connStr .= " sslmode='verify-full'";

        if (static::supportsDirectSslNegotiation()) {
            $connStr .= " sslnegotiation='direct'";
        }

        $connection = @pg_connect($connStr);

        if ($connection === false) {
            throw new ConnectionException('Failed to connect to Aurora DSQL.');
        }

        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'dsql';
    }

    public function getQueryTranslator(): QueryTranslatorInterface
    {
        return new AuroraDsqlQueryTranslator();
    }

    /**
     * Execute query with OCC retry (only outside transactions).
     *
     * Inside a transaction, individual statements are not retried — the
     * entire transaction must be retried via transaction(). This matches
     * the AWS DsqlPdo::exec() semantics.
     */
    protected function doExecuteQuery(string $sql, array $params = []): Result
    {
        $this->ensureTokenFresh();

        if ($this->occMaxRetries === 0 || $this->inTransaction()) {
            return parent::doExecuteQuery($sql, $params);
        }

        return $this->executeWithOccRetry(fn(): Result => parent::doExecuteQuery($sql, $params));
    }

    protected function doExecuteStatement(string $sql, array $params = []): int
    {
        $this->ensureTokenFresh();

        if ($this->occMaxRetries === 0 || $this->inTransaction()) {
            return parent::doExecuteStatement($sql, $params);
        }

        return $this->executeWithOccRetry(fn(): int => parent::doExecuteStatement($sql, $params));
    }

    // ── Transaction with OCC retry ──

    /**
     * Execute a callback inside a transaction with automatic OCC retry.
     *
     * On OCC conflict (SQLSTATE 40001, OC000, OC001), the transaction is
     * rolled back and the entire callback is re-executed with exponential
     * backoff. This matches the AWS DsqlPdo::transaction() pattern.
     *
     * The callback should NOT call beginTransaction() or commit().
     *
     * @template T
     *
     * @param callable(self): T $callback
     *
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        return $this->executeWithOccRetry(function () use ($callback): mixed {
            $this->beginTransaction();

            try {
                $result = $callback($this);
                $this->commit();

                return $result;
            } catch (\Throwable $e) {
                if ($this->inTransaction()) {
                    try {
                        $this->rollBack();
                    } catch (DriverException $rollbackEx) {
                        $this->logger?->error(\sprintf(
                            '[AuroraDsql] rollBack() failed during OCC retry: %s',
                            $rollbackEx->getMessage(),
                        ));
                    }
                }

                throw $e;
            }
        });
    }

    // ── Token management ──

    /**
     * Close connection if token is approaching expiry — doConnect() will regenerate.
     */
    private function ensureTokenFresh(): void
    {
        if ($this->tokenExpiresAt === null || $this->hasStaticToken) {
            return;
        }

        if (new \DateTimeImmutable() < $this->tokenExpiresAt) {
            return;
        }

        $this->close();
    }

    /**
     * Generate IAM authentication token via SigV4 presigned URL.
     *
     * Uses the same pattern as ElastiCacheIamTokenGenerator.
     */
    private function generateToken(
        string $endpoint,
        string $region,
        string $username,
        int $tokenDurationSecs,
        ?CredentialProvider $credentialProvider,
    ): string {
        if (!class_exists(Configuration::class)) {
            throw new ConnectionException(
                'Aurora DSQL requires async-aws/core for IAM token generation. '
                . 'Install it via: composer require async-aws/core. '
                . 'Alternatively, provide a pre-generated token via the constructor.',
            );
        }

        $provider = $credentialProvider ?? ChainProvider::createDefaultChain();
        $credentials = $provider->getCredentials(
            Configuration::create(['region' => $region]),
        );

        if ($credentials === null) {
            throw new ConnectionException(
                'Unable to resolve AWS credentials for Aurora DSQL.',
            );
        }

        $action = $username === 'admin' ? 'DbConnectAdmin' : 'DbConnect';

        $request = new Request('GET', '/', [], [], StringStream::create(''));
        $request->setEndpoint(\sprintf('https://%s', $endpoint));
        $request->setQueryAttribute('Action', $action);

        $expiresAt = new \DateTimeImmutable(\sprintf('+%d seconds', $tokenDurationSecs));
        $context = new RequestContext([
            'expirationDate' => $expiresAt,
        ]);

        $signer = new SignerV4('dsql', $region);
        $signer->presign($request, $credentials, $context);

        // Track token expiry with refresh margin
        $this->tokenExpiresAt = $expiresAt->modify(
            \sprintf('-%d seconds', self::TOKEN_REFRESH_MARGIN_SECS),
        );

        return str_replace('https://', '', $request->getEndpoint());
    }

    // ── OCC Retry ──

    /**
     * Execute an operation with OCC retry (exponential backoff + jitter).
     *
     * @template T
     *
     * @param \Closure(): T $operation
     *
     * @return T
     */
    private function executeWithOccRetry(\Closure $operation): mixed
    {
        if ($this->occMaxRetries === 0) {
            return $operation();
        }

        $waitMs = self::OCC_INITIAL_WAIT_MS;

        for ($attempt = 0; $attempt <= $this->occMaxRetries; ++$attempt) {
            try {
                return $operation();
            } catch (DriverException $e) {
                if (!self::isOccError($e) || $attempt === $this->occMaxRetries) {
                    throw $e;
                }

                $jitter = random_int(0, $waitMs);
                $sleepMs = $waitMs + $jitter;

                $this->logger?->warning(\sprintf(
                    '[AuroraDsql] OCC conflict, retrying (attempt %d/%d, wait %.2fs)',
                    $attempt + 1,
                    $this->occMaxRetries,
                    $sleepMs / 1000.0,
                ));

                usleep($sleepMs * 1000);
                $waitMs = (int) min($waitMs * self::OCC_MULTIPLIER, self::OCC_MAX_WAIT_MS);
            }
        }

        throw new DriverException('OCC max retries exceeded');
    }

    /**
     * Check if an exception is an OCC conflict error.
     *
     * SQLSTATE codes: 40001 (serialization failure), OC000/OC001 (DSQL-specific).
     */
    private static function isOccError(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '40001')
            || str_contains($message, 'OC000')
            || str_contains($message, 'OC001');
    }
}
