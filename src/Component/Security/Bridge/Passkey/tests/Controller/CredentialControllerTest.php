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
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\Passkey\Controller\CredentialController;
use WPPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WPPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;
use WPPack\Component\Security\Bridge\Passkey\Tests\Ceremony\InMemoryCredentialRepository;

#[CoversClass(CredentialController::class)]
final class CredentialControllerTest extends TestCase
{
    private int $userId = 0;

    protected function setUp(): void
    {
        $this->userId = (int) wp_insert_user([
            'user_login' => 'pk_cred_' . uniqid(),
            'user_email' => 'pk_cred_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);
        wp_set_current_user($this->userId);
    }

    protected function tearDown(): void
    {
        wp_set_current_user(0);
        wp_delete_user($this->userId);
    }

    private function controller(?InMemoryCredentialRepository $repo = null): CredentialController
    {
        return new CredentialController(
            $repo ?? new InMemoryCredentialRepository(),
            new AuthenticationSession(),
        );
    }

    private function credential(int $id, int $userId, string $name = 'Test'): PasskeyCredential
    {
        return new PasskeyCredential(
            id: $id,
            userId: $userId,
            credentialId: 'cred-' . $id,
            publicKey: 'pk',
            counter: 0,
            transports: ['internal'],
            deviceName: $name,
            aaguid: '00000000-0000-0000-0000-000000000000',
            backupEligible: false,
            createdAt: new \DateTimeImmutable('2024-01-01T12:00:00+00:00'),
            lastUsedAt: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\WPPack\Component\HttpFoundation\JsonResponse $response): array
    {
        /** @var array<string, mixed> */
        return json_decode($response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    private function request(mixed $body = null): \WP_REST_Request
    {
        $req = new \WP_REST_Request();
        if ($body !== null) {
            $req->set_header('content-type', 'application/json');
            $req->set_body(json_encode($body, \JSON_THROW_ON_ERROR));
        }

        return $req;
    }

    #[Test]
    public function listReturnsOnlyCurrentUsersCredentials(): void
    {
        $repo = new InMemoryCredentialRepository();
        $repo->save($this->credential(1, $this->userId, 'Mine'));
        $repo->save($this->credential(2, $this->userId + 1000, 'Other'));

        $response = $this->controller($repo)->list($this->request());

        self::assertSame(200, $response->statusCode);
        $body = json_decode($response->content, true);
        self::assertCount(1, $body);
        self::assertSame('Mine', $body[0]['deviceName']);
        self::assertSame('cred-1', $body[0]['credentialId']);
        self::assertArrayHasKey('createdAt', $body[0]);
    }

    #[Test]
    public function listIncludesSerializedFields(): void
    {
        $repo = new InMemoryCredentialRepository();
        $repo->save($this->credential(5, $this->userId, 'My key'));

        $response = $this->controller($repo)->list($this->request());
        $body = json_decode($response->content, true);

        self::assertSame(5, $body[0]['id']);
        self::assertSame('00000000-0000-0000-0000-000000000000', $body[0]['aaguid']);
        self::assertFalse($body[0]['backupEligible']);
        self::assertSame(['internal'], $body[0]['transports']);
        self::assertNull($body[0]['lastUsedAt']);
    }

    #[Test]
    public function updateRenamesDeviceWhenOwnedByUser(): void
    {
        $repo = new class extends InMemoryCredentialRepository {
            public ?string $renamedTo = null;
            public ?int $renamedId = null;

            public function updateDeviceName(int $id, string $name): void
            {
                $this->renamedId = $id;
                $this->renamedTo = $name;
            }
        };
        $repo->save($this->credential(7, $this->userId));

        $response = $this->controller($repo)->update(7, $this->request(['deviceName' => '  New Name  ']));

        self::assertSame(200, $response->statusCode);
        self::assertSame(['success' => true], $this->decode($response));
        self::assertSame(7, $repo->renamedId);
        self::assertSame('New Name', $repo->renamedTo, 'trimmed');
    }

    #[Test]
    public function updateReturns404WhenCredentialDoesNotBelongToUser(): void
    {
        $repo = new InMemoryCredentialRepository();
        $repo->save($this->credential(10, $this->userId + 9999));

        $response = $this->controller($repo)->update(10, $this->request(['deviceName' => 'X']));

        self::assertSame(404, $response->statusCode);
        self::assertSame('Credential not found.', $this->decode($response)['error']);
    }

    #[Test]
    public function updateIgnoresEmptyDeviceNameAfterTrim(): void
    {
        $repo = new class extends InMemoryCredentialRepository {
            public int $calls = 0;

            public function updateDeviceName(int $id, string $name): void
            {
                ++$this->calls;
            }
        };
        $repo->save($this->credential(11, $this->userId));

        $response = $this->controller($repo)->update(11, $this->request(['deviceName' => '   ']));

        self::assertSame(200, $response->statusCode);
        self::assertSame(0, $repo->calls, 'whitespace-only name is not persisted');
    }

    #[Test]
    public function updateIgnoresNonStringDeviceName(): void
    {
        $repo = new class extends InMemoryCredentialRepository {
            public int $calls = 0;

            public function updateDeviceName(int $id, string $name): void
            {
                ++$this->calls;
            }
        };
        $repo->save($this->credential(12, $this->userId));

        $response = $this->controller($repo)->update(12, $this->request(['deviceName' => 12345]));

        self::assertSame(200, $response->statusCode);
        self::assertSame(0, $repo->calls);
    }

    #[Test]
    public function updateTruncatesLongDeviceNames(): void
    {
        $repo = new class extends InMemoryCredentialRepository {
            public string $renamedTo = '';

            public function updateDeviceName(int $id, string $name): void
            {
                $this->renamedTo = $name;
            }
        };
        $repo->save($this->credential(13, $this->userId));

        $longName = str_repeat('漢', 400);
        $response = $this->controller($repo)->update(13, $this->request(['deviceName' => $longName]));

        self::assertSame(200, $response->statusCode);
        self::assertSame(255, mb_strlen($repo->renamedTo));
    }

    #[Test]
    public function deleteRemovesOwnedCredential(): void
    {
        $repo = new class extends InMemoryCredentialRepository {
            public int $deletedId = 0;

            public function delete(int $id): void
            {
                $this->deletedId = $id;
                parent::delete($id);
            }
        };
        $repo->save($this->credential(20, $this->userId));

        $response = $this->controller($repo)->delete(20);

        self::assertSame(200, $response->statusCode);
        self::assertSame(20, $repo->deletedId);
    }

    #[Test]
    public function deleteReturns404ForUnownedCredential(): void
    {
        $repo = new InMemoryCredentialRepository();
        $repo->save($this->credential(21, $this->userId + 9999));

        $response = $this->controller($repo)->delete(21);

        self::assertSame(404, $response->statusCode);
    }

    #[Test]
    public function deleteReturns404WhenCredentialDoesNotExist(): void
    {
        $response = $this->controller()->delete(9999);

        self::assertSame(404, $response->statusCode);
    }

    #[Test]
    public function isLoggedInReflectsCurrentSession(): void
    {
        $controller = $this->controller();

        self::assertTrue($controller->isLoggedIn($this->request()));

        wp_set_current_user(0);
        self::assertFalse($controller->isLoggedIn($this->request()));
    }
}
