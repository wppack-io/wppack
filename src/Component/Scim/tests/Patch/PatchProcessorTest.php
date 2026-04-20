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
use WPPack\Component\Scim\Exception\MutabilityException;
use WPPack\Component\Scim\Patch\PatchOperation;
use WPPack\Component\Scim\Patch\PatchProcessor;
use WPPack\Component\Scim\Patch\PatchRequest;
use WPPack\Component\Scim\Schema\ScimConstants;

#[CoversClass(PatchProcessor::class)]
#[CoversClass(PatchRequest::class)]
#[CoversClass(PatchOperation::class)]
final class PatchProcessorTest extends TestCase
{
    private PatchProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new PatchProcessor();
    }

    /**
     * @param list<array{op: string, path?: string|null, value?: mixed}> $ops
     */
    private static function request(array $ops): PatchRequest
    {
        return PatchRequest::fromArray([
            'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
            'Operations' => $ops,
        ]);
    }

    // ── add ──────────────────────────────────────────────────────────

    #[Test]
    public function addSetsValueAtPath(): void
    {
        $result = $this->processor->apply(
            ['nickName' => 'old'],
            self::request([['op' => 'add', 'path' => 'nickName', 'value' => 'new']]),
        );

        self::assertSame(['nickName' => 'new'], $result);
    }

    #[Test]
    public function addMergesObjectWhenNoPath(): void
    {
        $result = $this->processor->apply(
            ['nickName' => 'alice'],
            self::request([['op' => 'add', 'value' => ['nickName' => 'a', 'displayName' => 'A']]]),
        );

        self::assertSame(['nickName' => 'a', 'displayName' => 'A'], $result);
    }

    #[Test]
    public function addWithoutPathAppendsToListsWithinObject(): void
    {
        $resource = ['emails' => [['value' => 'a@example.com']]];
        $result = $this->processor->apply(
            $resource,
            self::request([[
                'op' => 'add',
                'value' => ['emails' => [['value' => 'b@example.com']]],
            ]]),
        );

        self::assertSame([
            'emails' => [
                ['value' => 'a@example.com'],
                ['value' => 'b@example.com'],
            ],
        ], $result);
    }

    #[Test]
    public function addAppendsToMultiValuedAttribute(): void
    {
        $resource = ['emails' => [['value' => 'a@example.com']]];
        $result = $this->processor->apply(
            $resource,
            self::request([[
                'op' => 'add',
                'path' => 'emails',
                'value' => [['value' => 'b@example.com']],
            ]]),
        );

        self::assertSame([
            'emails' => [
                ['value' => 'a@example.com'],
                ['value' => 'b@example.com'],
            ],
        ], $result);
    }

    #[Test]
    public function addWithoutPathRequiresObjectValue(): void
    {
        $this->expectException(InvalidPatchException::class);
        $this->processor->apply([], self::request([['op' => 'add', 'value' => 'scalar']]));
    }

    #[Test]
    public function addWithPathRequiresValue(): void
    {
        $this->expectException(InvalidPatchException::class);
        $this->processor->apply(
            [],
            self::request([['op' => 'add', 'path' => 'nickName', 'value' => null]]),
        );
    }

    // ── replace ──────────────────────────────────────────────────────

    #[Test]
    public function replaceMergesWhenNoPath(): void
    {
        $result = $this->processor->apply(
            ['nickName' => 'old', 'title' => 'Dev'],
            self::request([['op' => 'replace', 'value' => ['nickName' => 'new']]]),
        );

        self::assertSame(['nickName' => 'new', 'title' => 'Dev'], $result);
    }

    #[Test]
    public function replaceSetsValueAtPath(): void
    {
        $result = $this->processor->apply(
            ['name' => ['givenName' => 'Alice']],
            self::request([['op' => 'replace', 'path' => 'name.givenName', 'value' => 'Alicia']]),
        );

        self::assertSame(['name' => ['givenName' => 'Alicia']], $result);
    }

    #[Test]
    public function replaceWithoutPathRequiresObjectValue(): void
    {
        $this->expectException(InvalidPatchException::class);
        $this->processor->apply([], self::request([['op' => 'replace', 'value' => 'scalar']]));
    }

    #[Test]
    public function replaceWithPathRequiresValue(): void
    {
        $this->expectException(InvalidPatchException::class);
        $this->processor->apply(
            [],
            self::request([['op' => 'replace', 'path' => 'nickName', 'value' => null]]),
        );
    }

    // ── remove ───────────────────────────────────────────────────────

    #[Test]
    public function removesAtPath(): void
    {
        $result = $this->processor->apply(
            ['nickName' => 'a', 'title' => 't'],
            self::request([['op' => 'remove', 'path' => 'nickName']]),
        );

        self::assertSame(['title' => 't'], $result);
    }

    #[Test]
    public function removeRequiresPath(): void
    {
        $this->expectException(InvalidPatchException::class);
        $this->processor->apply([], self::request([['op' => 'remove']]));
    }

    #[Test]
    public function removeWithValueFiltersMultiValuedAttribute(): void
    {
        // Azure AD group-member removal pattern: {op:remove, path:members, value:[{value:"id"}]}
        $resource = [
            'members' => [
                ['value' => '1', 'display' => 'alice'],
                ['value' => '2', 'display' => 'bob'],
                ['value' => '3', 'display' => 'carol'],
            ],
        ];

        $result = $this->processor->apply(
            $resource,
            self::request([[
                'op' => 'remove',
                'path' => 'members',
                'value' => [['value' => '1'], ['value' => '3']],
            ]]),
        );

        self::assertSame([
            'members' => [['value' => '2', 'display' => 'bob']],
        ], $result);
    }

    #[Test]
    public function removeWithValueRequiresEachItemToHaveValueKey(): void
    {
        $resource = ['members' => [['value' => '1']]];

        $this->expectException(InvalidPatchException::class);
        $this->processor->apply(
            $resource,
            self::request([[
                'op' => 'remove',
                'path' => 'members',
                'value' => [['display' => 'alice']],
            ]]),
        );
    }

    // ── immutability ─────────────────────────────────────────────────

    #[Test]
    public function cannotReplaceUserNameAtPath(): void
    {
        $this->expectException(MutabilityException::class);
        $this->processor->apply(
            ['userName' => 'alice'],
            self::request([['op' => 'replace', 'path' => 'userName', 'value' => 'bob']]),
        );
    }

    #[Test]
    public function cannotReplaceIdAtPath(): void
    {
        $this->expectException(MutabilityException::class);
        $this->processor->apply(
            ['id' => 'abc'],
            self::request([['op' => 'replace', 'path' => 'id', 'value' => 'xyz']]),
        );
    }

    #[Test]
    public function cannotChangeUserNameViaPathlessAdd(): void
    {
        $this->expectException(MutabilityException::class);
        $this->processor->apply(
            ['userName' => 'alice'],
            self::request([['op' => 'add', 'value' => ['userName' => 'bob']]]),
        );
    }

    #[Test]
    public function addingSameUserNameIsAllowed(): void
    {
        $result = $this->processor->apply(
            ['userName' => 'alice'],
            self::request([['op' => 'add', 'value' => ['userName' => 'alice', 'title' => 'Dev']]]),
        );

        self::assertSame(['userName' => 'alice', 'title' => 'Dev'], $result);
    }

    #[Test]
    public function cannotRemoveUserName(): void
    {
        $this->expectException(MutabilityException::class);
        $this->processor->apply(
            ['userName' => 'alice'],
            self::request([['op' => 'remove', 'path' => 'userName']]),
        );
    }

    #[Test]
    public function immutabilityRejectsNestedPathUnderImmutableRoot(): void
    {
        // "id.foo" shares the immutable root "id" and must be rejected.
        $this->expectException(MutabilityException::class);
        $this->processor->apply(
            ['id' => ['foo' => 'bar']],
            self::request([['op' => 'replace', 'path' => 'id.foo', 'value' => 'x']]),
        );
    }

    // ── PatchRequest::fromArray ──────────────────────────────────────

    #[Test]
    public function requestRejectsMissingPatchOpSchema(): void
    {
        $this->expectException(InvalidPatchException::class);
        PatchRequest::fromArray([
            'Operations' => [['op' => 'add', 'value' => ['nickName' => 'x']]],
        ]);
    }

    #[Test]
    public function requestRejectsNonArrayOperation(): void
    {
        $this->expectException(InvalidPatchException::class);
        PatchRequest::fromArray([
            'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
            'Operations' => ['not-an-array'],
        ]);
    }

    #[Test]
    public function requestRejectsUnknownOperationName(): void
    {
        $this->expectException(InvalidPatchException::class);
        PatchRequest::fromArray([
            'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
            'Operations' => [['op' => 'mutate', 'value' => 1]],
        ]);
    }

    #[Test]
    public function requestRejectsEmptyOperationsList(): void
    {
        $this->expectException(InvalidPatchException::class);
        PatchRequest::fromArray([
            'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
            'Operations' => [],
        ]);
    }

    #[Test]
    public function requestNormalisesOperationNameCase(): void
    {
        $request = PatchRequest::fromArray([
            'schemas' => [ScimConstants::PATCH_OP_SCHEMA],
            'Operations' => [['op' => 'AdD', 'path' => 'nickName', 'value' => 'x']],
        ]);

        self::assertCount(1, $request->operations);
        self::assertSame('add', $request->operations[0]->op);
    }

    #[Test]
    public function appliesMultipleOperationsInOrder(): void
    {
        $result = $this->processor->apply(
            ['title' => 'Dev'],
            self::request([
                ['op' => 'replace', 'path' => 'title', 'value' => 'Staff'],
                ['op' => 'add', 'path' => 'nickName', 'value' => 'alice'],
                ['op' => 'remove', 'path' => 'title'],
            ]),
        );

        self::assertSame(['nickName' => 'alice'], $result);
    }
}
