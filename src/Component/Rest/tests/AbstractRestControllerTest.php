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

namespace WPPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Role\Exception\AccessDeniedException;
use WPPack\Component\Security\Tests\SecurityTestTrait;

final class AbstractRestControllerTest extends TestCase
{
    use SecurityTestTrait;

    private object $controller;

    protected function setUp(): void
    {
        $this->controller = new class extends AbstractRestController {
            public function callJson(mixed $data, int $statusCode = 200, array $headers = []): JsonResponse
            {
                return $this->json($data, $statusCode, $headers);
            }

            public function callCreated(mixed $data = null, array $headers = []): JsonResponse
            {
                return $this->created($data, $headers);
            }

            public function callNoContent(array $headers = []): Response
            {
                return $this->noContent($headers);
            }

            public function callResponse(mixed $data = null, int $statusCode = 200, array $headers = []): Response
            {
                return $this->response($data, $statusCode, $headers);
            }

            public function callGetUser(): ?\WP_User
            {
                return $this->getUser();
            }

            public function callIsGranted(string $attribute, mixed $subject = null): bool
            {
                return $this->isGranted($attribute, $subject);
            }

            public function callDenyAccessUnlessGranted(string $attribute, mixed $subject = null, string $message = 'Access Denied.'): void
            {
                $this->denyAccessUnlessGranted($attribute, $subject, $message);
            }
        };
    }

    #[Test]
    public function jsonReturnsJsonResponse(): void
    {
        $response = $this->controller->callJson(['key' => 'value'], 200, ['X-Test' => 'yes']);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(['key' => 'value'], $response->data);
        self::assertSame(200, $response->statusCode);
        self::assertArrayHasKey('X-Test', $response->headers);
        self::assertSame('yes', $response->headers['X-Test']);
    }

    #[Test]
    public function jsonDefaultValues(): void
    {
        $response = $this->controller->callJson(null);

        self::assertNull($response->data);
        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function createdReturns201(): void
    {
        $response = $this->controller->callCreated(['id' => 1]);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(['id' => 1], $response->data);
        self::assertSame(201, $response->statusCode);
    }

    #[Test]
    public function noContentReturns204(): void
    {
        $response = $this->controller->callNoContent();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('', $response->content);
        self::assertSame(204, $response->statusCode);
    }

    #[Test]
    public function responseReturnsResponse(): void
    {
        $response = $this->controller->callResponse('data', 202, ['X-Header' => 'val']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('data', $response->content);
        self::assertSame(202, $response->statusCode);
        self::assertSame(['X-Header' => 'val'], $response->headers);
    }

    #[Test]
    public function getUserReturnsSecurity(): void
    {
        $user = new \WP_User();
        $user->ID = 42;

        $security = $this->createSecurity(user: $user);
        $this->controller->setSecurity($security);

        self::assertSame($user, $this->controller->callGetUser());
    }

    #[Test]
    public function getUserThrowsWithoutSecurity(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Security is not available');

        $this->controller->callGetUser();
    }

    #[Test]
    public function isGrantedDelegatesToSecurity(): void
    {
        $security = $this->createSecurity(granted: true);
        $this->controller->setSecurity($security);

        self::assertTrue($this->controller->callIsGranted('edit_posts'));
    }

    #[Test]
    public function isGrantedThrowsWithoutSecurity(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Security is not available');

        $this->controller->callIsGranted('edit_posts');
    }

    #[Test]
    public function denyAccessUnlessGrantedDelegatesToSecurity(): void
    {
        $security = $this->createSecurity(granted: false);
        $this->controller->setSecurity($security);

        $this->expectException(AccessDeniedException::class);

        $this->controller->callDenyAccessUnlessGranted('edit_posts');
    }

    #[Test]
    public function denyAccessUnlessGrantedThrowsWithoutSecurity(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Security is not available');

        $this->controller->callDenyAccessUnlessGranted('edit_posts');
    }
}
