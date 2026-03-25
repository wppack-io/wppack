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

namespace WpPack\Component\Cache\Bridge\ElastiCacheAuth;

use AsyncAws\Core\Configuration;
use AsyncAws\Core\Credentials\ChainProvider;
use AsyncAws\Core\Credentials\CredentialProvider;
use AsyncAws\Core\Request;
use AsyncAws\Core\RequestContext;
use AsyncAws\Core\Signer\SignerV4;
use AsyncAws\Core\Stream\StringStream;

final class ElastiCacheIamTokenGenerator
{
    private SignerV4 $signer;
    private CredentialProvider $credentialProvider;

    public function __construct(
        private readonly string $region,
        private readonly string $userId,
        ?CredentialProvider $credentialProvider = null,
    ) {
        $this->signer = new SignerV4('elasticache', $region);
        $this->credentialProvider = $credentialProvider ?? ChainProvider::createDefaultChain();
    }

    /**
     * Generate a short-lived IAM auth token.
     *
     * @param string $host Cache endpoint (e.g., "my-cluster.xxxxx.apne1.cache.amazonaws.com:6379")
     */
    public function generateToken(string $host): string
    {
        $credentials = $this->credentialProvider->getCredentials(
            Configuration::create(['region' => $this->region]),
        );

        if ($credentials === null) {
            throw new \RuntimeException('Unable to resolve AWS credentials for ElastiCache IAM authentication.');
        }

        // Build presign request
        // ElastiCache IAM token = presigned URL for "Action=connect&User=<userId>"
        $request = new Request('GET', '/', [], [], StringStream::create(''));
        $request->setEndpoint(sprintf('http://%s', $host));
        $request->setQueryAttribute('Action', 'connect');
        $request->setQueryAttribute('User', $this->userId);

        $context = new RequestContext([
            'expirationDate' => new \DateTimeImmutable('+15 minutes'),
        ]);

        $this->signer->presign($request, $credentials, $context);

        // Token = signed URL without "http://" prefix
        $endpoint = $request->getEndpoint();

        return str_replace('http://', '', $endpoint);
    }

    /**
     * Create a credential_provider closure for use with WPPACK_CACHE_OPTIONS.
     */
    public function createProvider(string $host): \Closure
    {
        return fn(): string => $this->generateToken($host);
    }
}
