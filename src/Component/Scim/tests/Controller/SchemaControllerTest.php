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
use WPPack\Component\Scim\Controller\SchemaController;
use WPPack\Component\Scim\Schema\ScimConstants;

#[CoversClass(SchemaController::class)]
final class SchemaControllerTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function bodyOf(\WPPack\Component\HttpFoundation\JsonResponse $response): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) $response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function listReturnsUserAndGroupSchemas(): void
    {
        $controller = new SchemaController('https://example.test');

        $response = $controller->list();

        self::assertSame(200, $response->statusCode);
        self::assertSame(ScimConstants::CONTENT_TYPE, $response->headers['Content-Type']);

        $body = $this->bodyOf($response);
        self::assertSame(2, $body['totalResults']);
        self::assertSame(
            [ScimConstants::USER_SCHEMA, ScimConstants::GROUP_SCHEMA],
            array_column($body['Resources'], 'id'),
        );
    }

    #[Test]
    public function getReturnsUserSchema(): void
    {
        $response = (new SchemaController())->get(ScimConstants::USER_SCHEMA);

        self::assertSame(200, $response->statusCode);
        $body = $this->bodyOf($response);
        self::assertSame(ScimConstants::USER_SCHEMA, $body['id']);
        self::assertSame('User', $body['name']);
    }

    #[Test]
    public function getReturnsGroupSchema(): void
    {
        $response = (new SchemaController())->get(ScimConstants::GROUP_SCHEMA);

        self::assertSame(200, $response->statusCode);
        self::assertSame(ScimConstants::GROUP_SCHEMA, $this->bodyOf($response)['id']);
    }

    #[Test]
    public function getUnknownSchemaReturns404ErrorShape(): void
    {
        $response = (new SchemaController())->get('urn:does:not:exist');

        self::assertSame(404, $response->statusCode);
        $body = $this->bodyOf($response);
        self::assertSame([ScimConstants::ERROR_SCHEMA], $body['schemas']);
        self::assertSame('404', $body['status']);
    }
}
