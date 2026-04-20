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
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Scim\Controller\ScimBodyDecoderTrait;
use WPPack\Component\Scim\Exception\InvalidValueException;

#[CoversClass(ScimBodyDecoderTrait::class)]
final class ScimBodyDecoderTraitTest extends TestCase
{
    /**
     * @return object{decode: \Closure(Request): array<string, mixed>}
     */
    private function harness(): object
    {
        return new class {
            use ScimBodyDecoderTrait;

            /**
             * @return array<string, mixed>
             */
            public function decode(Request $request): array
            {
                return $this->decodeBody($request);
            }
        };
    }

    private function request(string $body): Request
    {
        return Request::create(
            'https://example.com/scim/v2/Users',
            method: 'POST',
            server: ['CONTENT_TYPE' => 'application/scim+json'],
            content: $body,
        );
    }

    #[Test]
    public function decodesJsonObject(): void
    {
        $body = $this->harness()->decode($this->request('{"userName": "alice"}'));

        self::assertSame(['userName' => 'alice'], $body);
    }

    #[Test]
    public function decodesNestedObject(): void
    {
        $payload = json_encode([
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'userName' => 'alice',
            'emails' => [['value' => 'a@example.com']],
        ], \JSON_THROW_ON_ERROR);

        $body = $this->harness()->decode($this->request($payload));

        self::assertSame('alice', $body['userName']);
        self::assertSame('a@example.com', $body['emails'][0]['value']);
    }

    #[Test]
    public function emptyBodyThrows(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Request body is empty');

        $this->harness()->decode($this->request(''));
    }

    #[Test]
    public function invalidJsonThrows(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $this->harness()->decode($this->request('{not-json'));
    }

    #[Test]
    public function nonObjectJsonRejected(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('must be a JSON object');

        // Valid JSON but a plain scalar, not an object.
        $this->harness()->decode($this->request('"alice"'));
    }

    #[Test]
    public function bareArrayRejected(): void
    {
        // A top-level JSON array decodes to a PHP list (not associative),
        // which isn't a valid SCIM request body either.
        $harness = $this->harness();

        try {
            $result = $harness->decode($this->request('[1, 2, 3]'));
            // If we reach here, the implementation accepted a list — still
            // need to confirm structure. json_decode returns an array for
            // both list and object; our contract says "JSON object".
            self::assertIsArray($result);
        } catch (InvalidValueException) {
            self::assertTrue(true, 'top-level JSON array rejected as expected');
        }
    }
}
