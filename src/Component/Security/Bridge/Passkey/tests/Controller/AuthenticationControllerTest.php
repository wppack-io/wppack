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

namespace WPPack\Component\Security\Bridge\Passkey\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WPPack\Component\Security\Bridge\Passkey\Controller\AuthenticationController;
use WPPack\Component\Security\Bridge\Passkey\Tests\Ceremony\InMemoryCredentialRepository;
use WPPack\Component\Transient\TransientManager;

#[CoversClass(AuthenticationController::class)]
final class AuthenticationControllerTest extends TestCase
{
    private function controller(?InMemoryCredentialRepository $repo = null): AuthenticationController
    {
        $config = new PasskeyConfiguration(rpId: 'example.test');
        $repo = $repo ?? new InMemoryCredentialRepository();

        return new AuthenticationController(
            new CeremonyManager($config, $repo, new TransientManager()),
            $repo,
            $config,
            new AuthenticationSession(),
            new NullLogger(),
        );
    }

    private function request(array $body = []): \WP_REST_Request
    {
        $req = new \WP_REST_Request();
        $req->set_header('content-type', 'application/json');
        $req->set_body(json_encode($body, \JSON_THROW_ON_ERROR));

        return $req;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\WPPack\Component\HttpFoundation\JsonResponse $response): array
    {
        /** @var array<string, mixed> */
        return json_decode($response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function optionsReturnsChallengeEnvelope(): void
    {
        $response = $this->controller()->options($this->request());

        self::assertSame(200, $response->statusCode);
        $body = $this->decode($response);
        self::assertArrayHasKey('challengeKey', $body);
        self::assertArrayHasKey('challenge', $body);
        self::assertArrayHasKey('rpId', $body);
        self::assertStringStartsWith('wppack_passkey_challenge_', $body['challengeKey']);
    }

    #[Test]
    public function verifyRejectsMissingChallengeKey(): void
    {
        $response = $this->controller()->verify($this->request([]));

        self::assertSame(400, $response->statusCode);
        self::assertSame('Invalid or expired challenge.', $this->decode($response)['error']);
    }

    #[Test]
    public function verifyRejectsWhenChallengeTypeMismatches(): void
    {
        set_transient('wppack_passkey_challenge_regtype', [
            'type' => 'registration',
            'options' => '{}',
            'optionsClass' => \Webauthn\PublicKeyCredentialCreationOptions::class,
        ], 300);

        $response = $this->controller()->verify($this->request([
            'challengeKey' => 'wppack_passkey_challenge_regtype',
        ]));

        self::assertSame(400, $response->statusCode);
        self::assertSame('Invalid or expired challenge.', $this->decode($response)['error']);
    }

    #[Test]
    public function verifyFailsGracefullyOnMalformedCredential(): void
    {
        $options = $this->controller()->options($this->request());
        $challengeKey = $this->decode($options)['challengeKey'];

        $response = $this->controller()->verify($this->request([
            'challengeKey' => $challengeKey,
            'id' => 'x',
            'type' => 'public-key',
            'rawId' => 'not-base64',
            'response' => [],
        ]));

        self::assertSame(400, $response->statusCode);
        self::assertSame('Passkey authentication failed.', $this->decode($response)['error']);
    }

    #[Test]
    public function verifyConsumesChallengeExactlyOnce(): void
    {
        $options = $this->controller()->options($this->request());
        $challengeKey = $this->decode($options)['challengeKey'];

        $payload = [
            'challengeKey' => $challengeKey,
            'id' => 'x',
            'type' => 'public-key',
            'rawId' => 'not-base64',
            'response' => [],
        ];

        // First call consumes the challenge
        $this->controller()->verify($this->request($payload));

        // Second call sees "expired"
        $second = $this->controller()->verify($this->request($payload));
        self::assertSame(400, $second->statusCode);
        self::assertSame('Invalid or expired challenge.', $this->decode($second)['error']);
    }
}
