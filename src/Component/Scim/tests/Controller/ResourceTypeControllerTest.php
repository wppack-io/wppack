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

namespace WPPack\Component\Scim\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\Scim\Controller\ResourceTypeController;
use WPPack\Component\Scim\Schema\ScimConstants;

#[CoversClass(ResourceTypeController::class)]
final class ResourceTypeControllerTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function bodyOf(JsonResponse $response): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) $response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function listReturnsBothResourceTypes(): void
    {
        $response = (new ResourceTypeController('https://example.test'))->list();

        self::assertSame(200, $response->statusCode);
        self::assertSame(ScimConstants::CONTENT_TYPE, $response->headers['Content-Type']);

        $body = $this->bodyOf($response);
        self::assertSame(2, $body['totalResults']);
        self::assertSame(['User', 'Group'], array_column($body['Resources'], 'id'));
        self::assertStringStartsWith(
            'https://example.test/scim/v2/ResourceTypes/User',
            $body['Resources'][0]['meta']['location'],
        );
    }

    #[Test]
    public function getReturnsUserResourceType(): void
    {
        $response = (new ResourceTypeController())->get('User');

        self::assertSame(200, $response->statusCode);
        self::assertSame('User', $this->bodyOf($response)['id']);
    }

    #[Test]
    public function getReturnsGroupResourceType(): void
    {
        $response = (new ResourceTypeController())->get('Group');

        self::assertSame('Group', $this->bodyOf($response)['id']);
    }

    #[Test]
    public function getUnknownTypeReturns404(): void
    {
        $response = (new ResourceTypeController())->get('Device');

        self::assertSame(404, $response->statusCode);
        self::assertSame([ScimConstants::ERROR_SCHEMA], $this->bodyOf($response)['schemas']);
    }
}
