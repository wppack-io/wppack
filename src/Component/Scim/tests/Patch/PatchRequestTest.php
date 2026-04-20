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

namespace WPPack\Component\Scim\Tests\Patch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Exception\InvalidPatchException;
use WPPack\Component\Scim\Patch\PatchOperation;
use WPPack\Component\Scim\Patch\PatchRequest;
use WPPack\Component\Scim\Schema\ScimConstants;

#[CoversClass(PatchRequest::class)]
#[CoversClass(PatchOperation::class)]
final class PatchRequestTest extends TestCase
{
    #[Test]
    public function patchOperationCarriesOpPathAndValue(): void
    {
        $op = new PatchOperation(op: 'replace', path: 'userName', value: 'alice');

        self::assertSame('replace', $op->op);
        self::assertSame('userName', $op->path);
        self::assertSame('alice', $op->value);
    }

    #[Test]
    public function patchOperationAllowsNullPath(): void
    {
        $op = new PatchOperation(op: 'add', path: null, value: ['emails' => [['value' => 'a@b.com']]]);

        self::assertNull($op->path);
    }

    #[Test]
    public function fromArrayParsesValidPatch(): void
    {
        $request = PatchRequest::fromArray([
            'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
            'Operations' => [
                ['op' => 'replace', 'path' => 'userName', 'value' => 'alice'],
                ['op' => 'add', 'path' => 'emails', 'value' => [['value' => 'a@b.com']]],
                ['op' => 'Remove', 'path' => 'nickName'], // case-insensitive
            ],
        ]);

        self::assertCount(3, $request->operations);
        self::assertSame('replace', $request->operations[0]->op);
        self::assertSame('add', $request->operations[1]->op);
        self::assertSame('remove', $request->operations[2]->op, 'op is lowercased');
        self::assertNull($request->operations[2]->value);
    }

    #[Test]
    public function fromArrayRejectsMissingSchema(): void
    {
        $this->expectException(InvalidPatchException::class);
        $this->expectExceptionMessage('PatchOp schema');

        PatchRequest::fromArray([
            'Operations' => [['op' => 'replace', 'path' => 'x', 'value' => 'y']],
        ]);
    }

    #[Test]
    public function fromArrayRejectsWrongSchema(): void
    {
        $this->expectException(InvalidPatchException::class);

        PatchRequest::fromArray([
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:SearchRequest'],
            'Operations' => [['op' => 'replace', 'path' => 'x', 'value' => 'y']],
        ]);
    }

    #[Test]
    public function fromArrayRejectsUnknownOperation(): void
    {
        $this->expectException(InvalidPatchException::class);
        $this->expectExceptionMessage('Invalid patch operation');

        PatchRequest::fromArray([
            'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
            'Operations' => [['op' => 'upsert', 'path' => 'x', 'value' => 'y']],
        ]);
    }

    #[Test]
    public function fromArrayRejectsNonArrayOperationEntry(): void
    {
        $this->expectException(InvalidPatchException::class);
        $this->expectExceptionMessage('JSON object');

        PatchRequest::fromArray([
            'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
            'Operations' => ['not-an-array'],
        ]);
    }

    #[Test]
    public function fromArrayRejectsEmptyOperations(): void
    {
        $this->expectException(InvalidPatchException::class);
        $this->expectExceptionMessage('at least one operation');

        PatchRequest::fromArray([
            'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
            'Operations' => [],
        ]);
    }
}
